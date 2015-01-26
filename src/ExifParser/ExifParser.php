<?php

namespace MunGell\ExifParser;

use Illuminate\Support\Arr;
use Carbon\Carbon;

class ExifParser
{
    const DATETIME = 'Y:m:d H:i:s';

    protected $raw = [];
    protected $flat = [];

    public function __construct($raw)
    {
        if (is_array($raw)) {
            $this->raw = $raw;
        }
    }

    public static function from($raw)
    {
        return new static($raw);
    }

    public function get($key)
    {
        return Arr::get($this->raw, $key);
    }

    public function camera()
    {
        $camera = $this->getAny([
            'LocalizedCameraModel',
            'UniqueCameraModel',
            'Camera'
        ]);

        if (!$camera) {
            $camera = trim($this->get('Make') . ' ' . $this->get('Model'));
        }

        if ($camera) {
            return $camera;
        }

        return null;
    }

    public function owner()
    {
        return $this->get('CameraOwnerName');
    }

    public function artist()
    {
        return $this->get('Artist');
    }

    /**
     * Get location coordinates
     *
     * @todo use this for timezone identification
     * @return array|null
     */
    public function location()
    {
        $fields = [
            'GPSLatitudeRef',
            'GPSLatitude',
            'GPSLongitudeRef',
            'GPSLongitude'
        ];

        foreach ($fields as $field) {
            $fields[$field] = $this->get($field);
            if (!$fields[$field]) {
                return null;
            }
        }

        $output['latitude'] = $this->parseCoordinates($fields['GPSLatitude'], $fields['GPSLatitudeRef']);
        $output['longitude'] = $this->parseCoordinates($fields['GPSLongitude'], $fields['GPSLongitudeRef']);

        return $output;
    }

    public function created()
    {
        return $this->getDateTime('DateTimeOriginal');
    }

    public function scanned()
    {
        return $this->getDateTime('DateTimeDigitized');
    }

    public function lastModified()
    {
        return $this->getDateTime('DateTime');
    }

    public function mimetype()
    {
        return $this->get('MimeType');
    }

    private function getAny($keys)
    {
        foreach ($keys as $key) {
            $value = $this->get($key);
            if ($value) {
                return $value;
            }
        }

        return null;
    }

    private function getDateTime($key)
    {
        $datetime = $this->get($key);

        if ($datetime) {
            $datetime = $this->parseDateTime($datetime);
        }

        return $datetime;
    }

    private function parseDateTime($datetime)
    {
        return Carbon::createFromFormat(self::DATETIME, $datetime)->toFormattedDateString();
    }

    private function parseCoordinates($coordinates, $hemisphere)
    {
        for ($i = 0; $i < 3; $i++) {
            $part = explode('/', $coordinates[$i]);
            $coordinates[$i] = 0;

            if (count($part) == 1) {
                $coordinates[$i] = $part[0];
            } elseif (count($part) == 2) {
                $coordinates[$i] = floatval($part[0]) / floatval($part[1]);
            }
        }

        list($degrees, $minutes, $seconds) = $coordinates;
        $sign = ($hemisphere == 'W' || $hemisphere == 'S') ? -1 : 1;

        return $sign * ($degrees + $minutes / 60 + $seconds / 3600);
    }

}