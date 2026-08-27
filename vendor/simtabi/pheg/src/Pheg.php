<?php declare(strict_types=1);

namespace Simtabi\Pheg;

use Simtabi\Enekia\Vanilla\Validators;
use Simtabi\Pheg\Core\Support\Supports;
use Simtabi\Pheg\Toolbox\Arr\Arr;
use Simtabi\Pheg\Toolbox\Asset;
use Simtabi\Pheg\Toolbox\Avatar;
use Simtabi\Pheg\Toolbox\Base64;
use Simtabi\Pheg\Toolbox\Base64Uid;
use Simtabi\Pheg\Toolbox\Breadcrumbs;
use Simtabi\Pheg\Toolbox\Cli;
use Simtabi\Pheg\Toolbox\Colors\Colors;
use Simtabi\Pheg\Toolbox\CopyrightText;
use Simtabi\Pheg\Toolbox\File;
use Simtabi\Pheg\Toolbox\Ini;
use Simtabi\Pheg\Toolbox\Intel\Intel;
use Simtabi\Pheg\Toolbox\Localization\Countries\Countries;
use Simtabi\Pheg\Toolbox\CsvParser;
use Simtabi\Pheg\Toolbox\Data\DataFactory;
use Simtabi\Pheg\Toolbox\Distance\Calculate;
use Simtabi\Pheg\Toolbox\Helpers;
use Simtabi\Pheg\Toolbox\PhoneNumber\Exceptions\PhoneNumberParseException;
use Simtabi\Pheg\Toolbox\PhoneNumber\PhoneNumber;
use Simtabi\Pheg\Toolbox\PhoneNumber\Supports\PhoneNumberFormat;
use Simtabi\Pheg\Toolbox\Time\Time;
use Simtabi\Pheg\Toolbox\Email;
use Simtabi\Pheg\Toolbox\Env;
use Simtabi\Pheg\Toolbox\Media\File\FileSystem;
use Simtabi\Pheg\Toolbox\Filter;
use Simtabi\Pheg\Toolbox\HTML\Html;
use Simtabi\Pheg\Toolbox\Http\Http;
use Simtabi\Pheg\Toolbox\Humanizer\Humanizer;
use Simtabi\Pheg\Toolbox\Input;
use Simtabi\Pheg\Toolbox\Media\Media;
use Simtabi\Pheg\Toolbox\JSON\JSON;
use Simtabi\Pheg\Toolbox\Name;
use Simtabi\Pheg\Toolbox\Password;
use Simtabi\Pheg\Toolbox\PhpDocs;
use Simtabi\Pheg\Toolbox\Request;
use Simtabi\Pheg\Toolbox\Sanitize;
use Simtabi\Pheg\Toolbox\Serialize;
use Simtabi\Pheg\Toolbox\SimpleTimer;
use Simtabi\Pheg\Toolbox\SqlHandler;
use Simtabi\Pheg\Toolbox\Stats;
use Simtabi\Pheg\Toolbox\String\Str;
use Simtabi\Pheg\Toolbox\System;
use Simtabi\Pheg\Toolbox\Timer;
use Simtabi\Pheg\Toolbox\Transfigures\Transfigure;
use Simtabi\Pheg\Toolbox\Url;
use Simtabi\Pheg\Toolbox\Uuid;
use Simtabi\Pheg\Toolbox\Vars;
use Simtabi\Pheg\Toolbox\Xml;

class Pheg
{

    /**
     * Create class instance
     *
     * @version      1.0
     * @since        1.0
     */
    private static $instance;

    public static function getInstance() {
        if (!isset(self::$instance) || is_null(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    private function __construct(){}
    private function __clone() {}

    public function supports(): Supports
    {
        return Supports::getInstance(self::$instance);
    }

    public function validator(): Validators
    {
        return new Validators;
    }

    ///
    ///
    ///

    public function colors(): Colors
    {
        return new Colors();
    }

    public function countries(?string $basePath = null): Countries
    {
        return new Countries($basePath);
    }

    public function dataFactory(): DataFactory
    {
        return new DataFactory();
    }

    public function calculator(): Calculate
    {
        return new Calculate();
    }

    public function json(): JSON
    {
        return new JSON();
    }

    /**
     * @throws PhoneNumberParseException
     */
    public function phoneNumber(string $phoneNumber, ?string $regionCode = PhoneNumberFormat::DEFAULT_REGION): \Simtabi\Pheg\Toolbox\PhoneNumber\PhoneNumber
    {
        return PhoneNumber::parse($phoneNumber, $regionCode);
    }

    public function media(): Media
    {
        return new Media();
    }

    public function intel(): Intel
    {
        return new Intel();
    }

    public function transfigure(): Transfigure
    {
        return new Transfigure();
    }




    public function fileSystem(): FileSystem
    {
        return new FileSystem;
    }



    public function arr(): Arr
    {
        return new Arr;
    }

    public function asset(): Asset
    {
        return new Asset;
    }

    public function avatar(): Avatar
    {
        return new Avatar;
    }

    public function base64(): Base64
    {
        return new Base64;
    }

    public function base64Uid(): Base64Uid
    {
        return new Base64Uid;
    }

    public function breadcrumbs(?string $separator = '>'): Breadcrumbs
    {
        return new Breadcrumbs($separator);
    }

    public function cli(): Cli
    {
        return new Cli;
    }

    public function copyrightText(): CopyrightText
    {
        return new CopyrightText;
    }

    public function csvParser(): CsvParser
    {
        return new CsvParser;
    }

    public function email(): Email
    {
        return new Email;
    }

    public function env(): Env
    {
        return new Env;
    }

    public function file(): File
    {
        return new File();
    }

    public function filter(): Filter
    {
        return new Filter();
    }

    public function html(): Html
    {
        return new Html;
    }

    public function helpers(): Helpers
    {
        return new Helpers;
    }

    public function ini(): Ini
    {
        return new Ini();
    }

    public function http(): Http
    {
        return new Http;
    }

    public function humanizer(): Humanizer
    {
        return new Humanizer;
    }

    public function input(): Input
    {
        return new Input;
    }


    public function name(): Name
    {
        return new Name;
    }

    public function password(): Password
    {
        return new Password;
    }

    public function phpDocs(): PhpDocs
    {
        return new PhpDocs;
    }

    public function request(): Request
    {
        return new Request;
    }

    public function sanitize(): Sanitize
    {
        return new Sanitize;
    }

    public function serialize(): Serialize
    {
        return new Serialize;
    }

    public function simpleTimer(): SimpleTimer
    {
        return new SimpleTimer;
    }

    public function sqlHandler(): SqlHandler
    {
        return new SqlHandler;
    }

    public function stats(): Stats
    {
        return new Stats;
    }

    public function str(): Str
    {
        return new Str;
    }

    public function system(): System
    {
        return new System;
    }

    public function time(): Time
    {
        return new Time;
    }

    public function timer(): Timer
    {
        return new Timer;
    }

    public function url(): Url
    {
        return new Url;
    }

    public function uuid(): Uuid
    {
        return new Uuid;
    }

    public function vars(): Vars
    {
        return new Vars;
    }

    public function xml(): Xml
    {
        return (new Xml());
    }

}



