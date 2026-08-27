<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use Simtabi\Pheg\Toolbox\String\Str;
use Illuminate\Database\Eloquent\Model;
use stdClass;

final class Name
{

    public function __construct() {}

    public function make($object, $substitute = true): bool|string|null
    {

        $validate = pheg()->validator()->transfigure();

        if ($validate->isArray($object))
        {
            $object = pheg()->transfigure()->toObject($object);
        }

        if (!$validate->isObject($object))
        {
            return false;
        }

        $salutation = strtolower($object->salutation ?? '');
        $firstName  = ucfirst($object->first_name    ?? '');
        $lastName   = ucfirst($object->last_name     ?? '');
        $username   = ucfirst($object->username      ?? '');
        $email      = $object->email;

        if (!empty($salutation) && !empty($firstName) && !empty($lastName)) {
            $name = sprintf("%s. %s %s", pheg()->supports()->getSalutations($salutation), $firstName, $lastName);
        }elseif (!empty($firstName) && !empty($lastName)) {
            $name = sprintf("%s %s", $firstName, $lastName);
        }elseif (!empty($firstName)) {
            $name = $firstName;
        }elseif (!empty($lastName)) {
            $name = $lastName;
        }elseif (!empty($username)) {
            $name = $username;
        }else{
            $name = $email;
        }

        if ($substitute)
        {
            if (empty(!$firstName) && empty($lastName)) {
                $name = sprintf("%s (%s)", $firstName, $email);
            }elseif (empty(!$lastName) && empty($firstName)) {
                $name = sprintf("%s (%s)", $lastName, $email);
            }elseif (empty($firstName) && empty($lastName)){
                $name = $email;
            }
        }

        return $name;
    }

    public function makeInitials(string $string): string
    {
        return (new Str)->generateInitials($string);
    }

    public function makeRandomUsername(string $firstName = "John", string $lastName = "Doe", int $randNo = 1000): string
    {
        $buildFullName = $firstName . " " . $lastName;
        $usernameParts = array_filter(explode(" ", strtolower($buildFullName))); //explode and lowercase name
        $usernameParts = array_slice($usernameParts, 0, 2); //return only first two array part

        $part1 = (!empty($usernameParts[0])) ? substr($usernameParts[0], 0, 8) : ""; //cut first name to 8 letters
        $part2 = (!empty($usernameParts[1])) ? substr($usernameParts[1], 0, 5) : ""; //cut second name to 5 letters
        $part3 = ($randNo) ? rand(0, $randNo) : "";

        $out = $part1 . str_shuffle($part2) . $part3; //str_shuffle to randomly shuffle all characters

        return strtolower(trim($out));
    }

    public function usernameFromEmail(string $email): string
    {
        // Split the username and domain from the email
        $parts = explode('@', $email);
        return strtolower(trim($parts[0]));
    }

    public function name2username(string $firstname = "James", ?string $lastname = "Oduro", bool $extended = true, int $total = 200): array
    {

        $out = [];

        if ($extended) {

            $firstTwoChars = str_split($firstname, 2)[0];
            $firstChar     = str_split($firstname, 1)[0];

            /**
             * an array of numbers that may be used as suffix for the usernames index 0 would be the year
             * and index 1, 2 and 3 would be month, day and hour respectively.
             */
            $numSuffix     = explode('-', date('Y-m-d-H'));

            // create an array of nice possible usernames from the first name and last name
            array_push($out,
                $firstname,        // james
                $firstname.$numSuffix[0],  // james2019
                $firstname.$numSuffix[1],  // james12 i.e the month of reg
                $firstname.$numSuffix[2],  // james28 i.e the day of reg
                $firstname.$numSuffix[3]   // james13 i.e the hour of day of reg
            );

            if (!empty($lastname))
            {
                array_push($out,
                    $lastname,        // oduro
                    $firstname.$lastname,     // jamesoduro
                    $firstname.'_'.$lastname, // james_oduro
                    $firstChar.$lastname,     // joduro
                    $firstTwoChars.$lastname  // jaoduro,
                );
            }

        }else{

            $total = $total < 1 ? 1 : $total;
            for ($x = 0; $x <= $total; $x++)
            {

                $parts = array_filter(explode(" ", strtolower($firstname))); //explode and lowercase name
                $parts = array_slice($parts, 0, 2); //return only first two array part

                $part1 = (!empty($parts[0]))?substr($parts[0], 0,8):""; //cut first name to 8 letters
                $part2 = (!empty($parts[1]))?substr($parts[1], 0,5):""; //cut second name to 5 letters
                $part3 = ($x) ? rand($x, 999+$x) : "";

                $out[] = $part1. str_shuffle($part2). $part3; //str_shuffle to randomly shuffle all characters
            }

        }

        $suggestions = [];
        foreach ($out as $item)
        {
            $suggestions[] = strtolower(trim($item));
        }

        return $suggestions;
    }

}
