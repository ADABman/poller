<?php

namespace Poller\Tasks;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Exception;
use FreeDSx\Snmp\Exception\ConnectionException;
use Poller\DeviceIdentifiers\IdentifierInterface;
use Poller\Models\Device;
use Poller\Models\MonitoringTemplate;
use Poller\Models\SnmpError;
use Poller\Models\SnmpResult;
use Tries\SuffixTrie;

class SnmpGet implements Task
{
    private Device $device;
    private SuffixTrie $trie;

    /**
     * SnmpGet constructor.
     * @param Device $device
     * @param SuffixTrie $trie
     */
    public function __construct(Device $device, SuffixTrie $trie)
    {
        $this->device = $device;
        $this->trie = $trie;
    }

    /**
     * @param Environment $environment
     * @return SnmpError|SnmpResult
     */
    public function run(Environment $environment)
    {
        $snmp = $this->device->getSnmpClient();
        try {
            $snmpResult = new SnmpResult(
                $snmp->get(...$this->device->getMonitoringTemplate()->getOids())
            );

            $oid = $snmpResult->results()->get(MonitoringTemplate::SYSTEM_SYSOBJECT_ID);
            if ($oid && $oid->getValue()) {
                $results = $this->trie->search($oid->getValue()->__toString())
                    ->sortKeys()
                    ->limit(1);

                if (count($results) === 1) {
                    $className = $results[0];
                    $mapper = new $className($this->device);
                    if ($mapper instanceof IdentifierInterface) {
                        $mapper = $mapper->getMapper();
                    }
                    $snmpResult = $mapper->map($snmpResult);
                }
            }

            return $snmpResult;
        } catch (ConnectionException $e) {
            return new SnmpError(true, $e->getMessage());
        } catch (Exception $e) {
            return new SnmpError(false, $e->getMessage());
        } finally {
            unset($snmp);
        }
    }
}
