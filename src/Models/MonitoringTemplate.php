<?php

namespace Poller\Models;

class MonitoringTemplate
{
    const SYSTEM_SYSOBJECT_ID = '1.3.6.1.2.1.1.2.0';

    private bool $icmp;
    private bool $collectInterfaceStatistics;
    private int $snmpVersion;
    private $snmpCommunity;
    private $snmp3SecLevel;
    private $snmp3AuthProtocol;
    private $snmp3AuthPassphrase;
    private $snmp3PrivProtocol;
    private $snmp3PrivPassphrase;
    private $snmp3ContextName;
    private $snmp3ContextEngineID;
    private array $oids;

    public function __construct($monitoringTemplate)
    {
        $this->icmp = (bool)$monitoringTemplate->icmp;
        $this->collectInterfaceStatistics = (bool)$monitoringTemplate->collect_interface_statistics;
        $this->snmpVersion = (int)$monitoringTemplate->snmp_version;
        $this->snmpCommunity = $monitoringTemplate->snmp_community;
        $this->snmp3SecLevel = $monitoringTemplate->snmp3_sec_level;
        $this->snmp3AuthProtocol = $monitoringTemplate->snmp3_auth_protocol;
        $this->snmp3AuthPassphrase = $monitoringTemplate->snmp3_auth_passphrase;
        $this->snmp3PrivProtocol = $monitoringTemplate->snmp3_priv_protocol;
        $this->snmp3PrivPassphrase = $monitoringTemplate->snmp3_priv_passphrase;
        $this->snmp3ContextName = $monitoringTemplate->snmp3_context_name;
        $this->snmp3ContextEngineID = $monitoringTemplate->snmp3_context_engine_id;
        $this->oids = array_unique(array_merge([MonitoringTemplate::SYSTEM_SYSOBJECT_ID], $monitoringTemplate->oids));
    }

    /**
     * @return bool
     */
    public function getIcmp():bool
    {
        return $this->icmp;
    }

    /**
     * @return bool
     */
    public function getCollectInterfaceStatistics():bool
    {
        return $this->collectInterfaceStatistics;
    }

    /**
     * @return int
     */
    public function getSnmpVersion():int
    {
        switch ($this->snmpVersion) {
            case 2:
                return 2;
                break;
            case 3:
                return 3;
                break;
            default:
                return 1;
                break;
        }
    }

    /**
     * @return string
     */
    public function getSnmpCommunity():string
    {
        return $this->snmpCommunity;
    }

    /**
     * @return string
     */
    public function getSnmp3SecLevel():?string
    {
        return $this->snmp3SecLevel;
    }

    /**
     * @return string
     */
    public function getSnmp3AuthProtocol():?string
    {
        return $this->snmp3AuthProtocol;
    }

    /**
     * @return null|string
     */
    public function getSnmp3AuthPassphrase():?string
    {
        return $this->snmp3AuthPassphrase;
    }

    /**
     * @return string
     */
    public function getSnmp3PrivProtocol():?string
    {
        return $this->snmp3PrivProtocol;
    }

    /**
     * @return null|string
     */
    public function getSnmp3PrivPassphrase():?string
    {
        return $this->snmp3PrivPassphrase;
    }

    /**
     * @return null|string
     */
    public function getSnmp3ContextName():?string
    {
        return $this->snmp3ContextName;
    }

    /**
     * @return null|string
     */
    public function getSnmp3ContextEngineID():?string
    {
        return $this->snmp3ContextEngineID;
    }

    /**
     * @return array
     */
    public function getOids(): array
    {
        return $this->oids;
    }
}
