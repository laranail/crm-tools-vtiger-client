<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\Arr\Query;

use Simtabi\Pheg\Toolbox\Arr\Query\QueryEngine;

class ArrayQuery extends QueryEngine
{

    public function __construct($data = [])
    {
        if (is_array($data)) {
            $this->collect($data);
        } else {
            parent::__construct($data);
        }
    }

    public function readPath($file)
    {
        return '{}';
    }

    public function parseData($data)
    {

        return $this->collect([]);
    }


}
