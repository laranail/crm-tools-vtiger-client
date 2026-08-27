<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox\HTML;

final class Form
{

    public function __construct() {}

    public function data2selectbox(array|object $data, ?string $placeholderText = "Select something", string $nothingToSelectText = "Nothing to Select"): array
    {
        if (is_object($data))
        {
            $data = pheg()->transfigure()->object2Array($data);
        }

        if (empty($data)){
            return ['' => $nothingToSelectText];
        }

        if (!empty($placeholderText)) {
            return ['' => $placeholderText] + $data;
        }

        return $data;

    }

}
