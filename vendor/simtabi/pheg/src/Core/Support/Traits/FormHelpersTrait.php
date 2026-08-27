<?php declare(strict_types=1);

namespace Simtabi\Pheg\Core\Support\Traits;

trait FormHelpersTrait
{

    public function getFormReadyTimezones(?string $default = null)
    {
        if (!empty($default)) {
            return $this->pheg->time()->getTimezones()['flat'][$default] ?? null;
        }else{
            return $this->pheg->time()->getTimezones()['formed'];
        }
    }

    public function getFormReadyDatetimeFormats(?string $default = null, string $type = 'long'): array
    {
        $data = $this->pheg->arr()->fetch('datetime.'. trim($type), $this->getDatetimeFormats($default));
        $out  = [];
        if (!empty($data)) {
            foreach ($data as $k => $datum){
                $out[$k] = $datum['human'];
            }
        }
        return $out;
    }

    public function getFormReadyDateFormats(?string $default = null, string $type = 'short'): array
    {
        $data = $this->pheg->arr()->fetch('date.'. trim($type), $this->getDatetimeFormats($default));
        $out  = [];
        if (!empty($data)) {
            foreach ($data as $k => $datum){
                $out[$k] = $datum['human'];
            }
        }
        return $out;
    }

    public function getFormReadyTimeFormats(?string $default = null, string $type = 'short'): array
    {
        $data = $this->pheg->arr()->fetch('time.'. trim($type), $this->getDatetimeFormats($default));

        $out  = [];
        if (!empty($data)) {
            foreach ($data as $k => $datum){
                $out[$k] = $datum['human'];
            }
        }
        return $out;
    }

    public function getFormReadyJsFormats(?string $default = null, string $type = 'date'): array
    {
        $data = $this->pheg->arr()->fetch('js.'. trim($type), $this->getDatetimeFormats($default));
        $out  = [];
        if (!empty($data)) {
            foreach ($data as $k => $datum){
                $out[$k] = $datum['human'];
            }
        }
        return $out;
    }


}
