<?php declare(strict_types = 1);

namespace Simtabi\Pheg\Toolbox\HTML\Exceptions;

class Html2TextException extends \Exception {

    public mixed $more_info;

    public function __construct($message = "", $more_info = "") {
        parent::__construct($message);

        $this->more_info = $more_info;
    }

}