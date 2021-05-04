<?php

namespace Poller\DeviceMappers\MikroTik;

use Exception;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\Transmitter\NetworkStream;
use Poller\DeviceMappers\BaseDeviceMapper;
use Poller\Services\Log;
use Poller\Models\SnmpResult;
use Poller\Services\Formatter;
use Poller\Web\Services\Database;

class MikroTik extends BaseDeviceMapper
{
    private ?Client $client = null;

    public function map(SnmpResult $snmpResult)
    {
        $snmpResult = $this->getWirelessClients(parent::map($snmpResult));
        $snmpResult = $this->getLldpTable($snmpResult);
        return $this->getBridgingTable($snmpResult);
    }

    private function getLldpTable(SnmpResult $snmpResult):SnmpResult
    {
        $interfaces = $snmpResult->getInterfaces();

        try {
            $macResult = $this->walk("1.3.6.1.4.1.14988.1.1.11.1.1.3");
            $interfaceResult = $this->walk("1.3.6.1.4.1.14988.1.1.11.1.1.8");

            foreach ($macResult->getAll() as $oid => $value) {
                $boom = explode(".", $oid);
                $interfaceIndex = $boom[count($boom)-1];
                try {
                    $mac = Formatter::formatMac($value);

                    $interfaceNumber = $interfaceResult->get("1.3.6.1.4.1.14988.1.1.11.1.1.8.$interfaceIndex");
                    if(isset($interfaces[$interfaceNumber])) {
                        $existingMacs = $interfaces[$interfaceNumber]->getConnectedLayer2Macs();
                        $existingMacs[] = $mac;
                        $interfaces[$interfaceNumber]->setConnectedLayer2Macs($existingMacs);
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        } catch (Exception $e) {
            $log = new Log();
            $log->exception($e, [
                'ip' => $this->device->getIp(),
            ]);
        }

        $snmpResult->setInterfaces($interfaces);

        return $snmpResult;
    }

    /**
     * @param SnmpResult $snmpResult
     * @return array|mixed
     */
    private function getWirelessClients(SnmpResult $snmpResult):SnmpResult
    {
        $interfaces = $snmpResult->getInterfaces();
        try {
            $result = $this->walk("1.3.6.1.4.1.14988.1.1.1.2.1.1");
            foreach ($result->getAll() as $oid => $value) {
                $boom = explode(".", $oid);
                $interfaceIndex = $boom[count($boom)-1];
                try {
                    $mac = Formatter::formatMac($value);

                    if(isset($interfaces[$interfaceIndex])) {
                        $existingMacs = $interfaces[$interfaceIndex]->getConnectedLayer1Macs();
                        $existingMacs[] = $mac;
                        $interfaces[$interfaceIndex]->setConnectedLayer1Macs($existingMacs);
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        } catch (\Throwable $e) {
            $log = new Log();
            $log->exception($e, [
                'ip' => $this->device->getIp(),
            ]);
        }

        $snmpResult->setInterfaces($interfaces);
        return $snmpResult;
    }

    private function getBridgingTable(SnmpResult $snmpResult):SnmpResult
    {
        $client = $this->getClient();
        if (!$client) {
            return $snmpResult;
        }

        $interfaces = $snmpResult->getInterfaces();
        $interfacesByName = [];
        foreach ($interfaces as $interface) {
            $interfacesByName[$interface->getName()] = $interface;
        }

        $request = new Request('/interface/bridge/host/print');
        $request->setArgument('.proplist', '.id,local,on-interface');
        $responses = $client->sendSync($request);
        foreach ($responses as $response) {
            if ($response->getType() !== Response::TYPE_DATA && $response->getType() !== Response::TYPE_FINAL) {
                return $snmpResult;
            }

            $id = $response->getProperty('.id');
            if ($id !== null) {
                $mac = $response->getProperty('local');
                $interface = $response->getProperty('on-interface');
                if (isset($interfacesByName[$interface])) {
                    $existingMacs = $interfacesByName[$interface]->getConnectedLayer2Macs();
                    $existingMacs[] = $mac;
                    $interfacesByName[$interface]->setConnectedLayer2Macs($existingMacs);
                }
            }
        }

        $snmpResult->setInterfaces($interfaces);
        return $snmpResult;
    }

    /**
     * Get the API client
     * @return Client|null
     */
    private function getClient()
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $database = new Database();
        $credentials = $database->getCredential(Database::MIKROTIK_API);

        if ($credentials === null) {
            return null;
        }

        $context = stream_context_create(
            [
                'ssl' => [
                    'verify_peer' => false,
                    'allowed_self_signed' => true,
                    'verify_peer_name' => false,
                ]
            ]
        );

        try {
            $client = new Client(
                $this->device->getIp(),
                $credentials['username'],
                $credentials['password'],
                $credentials['port'],
                false,
                5,
                NetworkStream::CRYPTO_TLS,
                $context
            );
        } catch (Exception $e) {
            $log = new Log();
            $log->exception($e);
            return null;
        }

        $this->client = $client;
        return $this->client;
    }
}
