<?php

namespace ConnectionManager\Extra\Multiple;

use ConnectionManager\ConnectionManagerInterface;
use \UnderflowException;
use \InvalidArgumentException;

class ConnectionManagerSelective implements ConnectionManagerInterface
{
    private $targets = array();

    public function getConnection($host, $port)
    {
        return $this->getConnectionManagerFor($host, $port)->getConnection($host, $port);
    }

    public function addConnectionManager($connectionManager, $targetHost=null, $targetPort=null)
    {
        $this->targets []= array(
            'connectionManager' => $connectionManager,
            'matchHost' => $this->createMatcherHost($targetHost),
            'matchPort' => $this->createMatcherPort($targetPort)
        );
    }

    public function getConnectionManagerFor($targetHost, $targetPort)
    {
        foreach ($this->targets as $target) {
            if ($target['matchPort']($targetPort) && $target['matchHost']($targetHost)) {
                return $target['connectionManager'];
            }
        }
        throw new UnderflowException('No connection manager for given target found');
    }

    // null OR *
    // singlePort
    // startPort - targetPort
    // port1, port2, port3
    // startPort - targetPort, portAdditional
    public function createMatcherPort($pattern)
    {
        if ($targetPort === null || $targetHost === '*') {
            return function() {
                return true;
            };
        } else if (strpos($pattern, ',') !== false) {
            $checks = array();
            foreach (explode(',', $pattern) as $part) {
                $checks []= $this->createMatcherPort(trim($part));
            }
            return function ($port) use ($checks) {
                foreach ($checks as $check) {
                    if ($check($port)) {
                        return true;
                    }
                }
                return false;
            };
        } else if (preg_match('/^(\d+)$/', $pattern, $match)) {
            $single = $this->coercePort($match[1]);
            return function ($port) use ($single) {
                return ($port == $single);
            };
        } else if (preg_match('/^(\d+)\s*\-\s*(\d+)$/', $pattern, $match)) {
            $start = $this->coercePort($match[1]);
            $end   = $this->coercePort($match[2]);
            if ($start >= $end) {
                throw new InvalidArgumentException('Invalid port range given');
            }
            return function($port) use ($start, $end) {
                return ($port >= $start && $port <= $end);
            };
        } else {
             throw new InvalidArgumentException('Invalid port matcher given');
        }
    }

    private function coercePort($port)
    {
        // TODO: check 0-65535
        return (int)$port;
    }

    // null OR *
    // targetHostname
    // targetIp
    // TODO: targetIp/netmaskNum
    // TODO: targetIp/netmaskIp
    public function createMatcherHost($pattern)
    {
        if ($pattern === null || $pattern === '*') {
            return function() {
                return true;
            };
        } else if (is_string($pattern)) {
            $pattern = strtolower($pattern);
            return function($target) use ($pattern) {
                return fnmatch($pattern, strtolower($target));
            };
        } else {
            throw new InvalidArgumentException('Invalid host matcher given');
        }
    }
}