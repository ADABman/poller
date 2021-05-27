<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\Loop;
use Carbon\Carbon;
use GuzzleHttp\Client;
use League\BooBoo\BooBoo;
use League\BooBoo\Handler\LogHandler;
use League\CLImate\CLImate;
use Poller\Services\Log;
use Poller\Overrides\CommandLineFormatter;
use Poller\Pipelines\Fetcher;
use Poller\Services\Formatter;
use Poller\Services\Poller;
use Poller\Web\Services\Database;
use function Amp\Promise\all;

bootstrap();

Loop::run(function () {
    $poller = new Poller();
    $client = new Client();

    output("Starting polling loop...");
    $running = false;
    $lastRun = 0;
    Loop::repeat($msInterval = 1000, function ($watcherId) use (&$running, &$lastRun, $poller, $client) {
        if ($running === false && time() - $lastRun >= 60) {
            $running = true;
            $debug = getenv('SONAR_DEBUG_MODE') == 1;
            if ($debug === true) {
                output("---DEBUG MODE ENABLED---");
            }
            output("Starting polling cycle, fetching work from Sonar.");

            $fetcher = new Fetcher();
            $database = new Database();

            $sonarUrl = $database->get(Database::SONAR_URL);
            if (!$sonarUrl) {
                output("No Sonar URL defined, skipping.");
                return;
            }

            $fullUrl = 'https://' . $sonarUrl . '.sonar.software';

            try {
                $data = $fetcher->fetch($debug);
                $lastRun = time();
                $start = time();
                output("Obtained work, starting coroutines.");
                $results = yield all($poller->buildCoroutines($data->data));
                $timeTaken = time() - $start;
                output("Cycle completed in $timeTaken seconds, got " . count($results) . " results.");

                if (count($results) > 0) {
                    if ($debug === true) {
                        writeDebugLog($results, $timeTaken);
                        Loop::disable($watcherId);
                    } else {
                        try {
                            output("Submitting results to $fullUrl");
                            $response = $client->request('POST', "$fullUrl/api/batch_poller", [
                                'headers' => [
                                    'User-Agent' => "SonarPoller/" . get_version(),
                                    'Accept' => 'application/json',
                                    //'Content-Encoding' => 'gzip',
                                    'Accept-Encoding' => 'gzip',
                                    'Content-Type' => 'application/json',
                                ],
                                'body' => Formatter::formatMonitoringData($results, $timeTaken, false),
                            ]);
                            output($response->getStatusCode() . ' - ' . $response->getBody()->getContents());
                        } catch (Exception $e) {
                            output($e->getMessage(), true);
                        }
                    }
                }
            } catch (Throwable $e) {
                output("Failed to get work, got " . $e->getMessage());
            }

            $running = false;
        }
    });
});

/**
 * @param string $message
 * @param bool $error
 */
function output(string $message, bool $error = false)
{
    $log = new Log();
    $climate = new CLImate;
    $now = Carbon::now();
    if ($error === false) {
        $log->info($message);
        $climate->lightGreen("[{$now->toIso8601String()}] $message");
    } else {
        $log->error($message);
        $climate->red("[{$now->toIso8601String()}] $message");
    }
}

function bootstrap()
{
    $booboo = new BooBoo([
        new CommandLineFormatter()
    ]);
    $log = new Log();
    $booboo->pushHandler(new LogHandler($log->getLogger()));
    $booboo->setErrorPageFormatter(new CommandLineFormatter());
    $booboo->register();
}

function writeDebugLog($results, $timeTaken)
{
    output("Writing results to sonar_debug.log.");
    $handle = fopen(__DIR__ . '/sonar_debug.log', 'w');
    fwrite($handle, Formatter::formatMonitoringData($results, $timeTaken, false));
    fclose($handle);
}
