<?php

class SdpData
{
    protected $sdpData;

    public function __construct(string $sdpData)
    {
        $this->sdpData = $sdpData;
    }

    public function getMediaStreams(): array
    {
        $streams = [];
        $stream = null;
        $lines = preg_split('/\r\n/', $this->sdpData);
        foreach ($lines as $line) {
            if (isset($line[0])) {
                $paramValue = substr($line, 2);
                if ($line[0] === 'm') {
                    $streams[$paramValue] = [];
                    $stream = &$streams[$paramValue];
                } else if ($stream !== null && $line[0] === 'a') {
                    $paramParts = explode(':', $paramValue, 2);
                    $stream[$paramParts[0]] = $paramParts[1] ?? '';
                }
            }
        }

        return $streams;
    }
}

