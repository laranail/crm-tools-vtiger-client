<?php declare(strict_types=1);

namespace Simtabi\Pheg\Toolbox;

use Ramsey\Uuid\Codec\TimestampFirstCombCodec;
use Ramsey\Uuid\Generator\CombGenerator;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidInterface;
use Simtabi\Pheg\Core\Exceptions\InvalidUuidVersionException;

final class Uuid
{

    /**
     * The callback that should be used to generate UUIDs.
     *
     * @var callable
     */
    protected  $uuidFactory;

    public function __construct() {}

    /**
     * Generate a UUID (version 1,3,4 & 5).
     *
     * @return string
     * @throws InvalidUuidVersionException
     */
    public function generate(int $version = 4, ?string $uuidString = '')
    {
        if ($this->uuidFactory)
        {
            return call_user_func($this->uuidFactory);
        }

        return match ($version) {
            1       => RamseyUuid::uuid1()->toString(),
            3       => RamseyUuid::uuid3(RamseyUuid::NAMESPACE_DNS, $uuidString)->toString(),
            4       => RamseyUuid::uuid4()->toString(),
            5       => RamseyUuid::uuid5(RamseyUuid::NAMESPACE_DNS, $uuidString)->toString(),
            default => throw new InvalidUuidVersionException(),
        };
    }

    /**
     * Generates a "Time Ordered" UUID (version 4) which is generated in conjunction with the server timestamp.  Less unique, but
     * useful if ordering by time is important
     *
     */
    public function generateOrdered()
    {
        if ($this->uuidFactory) {
            return call_user_func($this->uuidFactory);
        }

        $factory = new UuidFactory();

        $factory->setRandomGenerator(new CombGenerator(
            $factory->getRandomGenerator(),
            $factory->getNumberConverter()
        ));

        $factory->setCodec(new TimestampFirstCombCodec(
            $factory->getUuidBuilder()
        ));

        return $factory->uuid4();
    }

    /**
     * Generates a test UUID with the model name as a prefix for easy distinction when testing
     *
     * @param $model
     *
     * @return string
     */
    public function generateReadableForTesting($model): string
    {
        $className   = strtolower(class_basename($model)) . '_';
        $numToRemove = strlen($className);
        $remaining   = (36 - (int) $numToRemove);

        return $className . substr($this->generate(), $numToRemove, $remaining);
    }

    /**
     * Set the callable that will be used to generate UUIDs.
     *
     * @param  callable|null  $factory
     * @return void
     */
    public function generateUsing(callable $factory = null)
    {
        $this->uuidFactory = $factory;
    }

    /**
     * Indicate that UUIDs should be created normally and not using a custom factory.
     *
     * @return void
     */
    public function generateNormally()
    {
        $this->uuidFactory = null;
    }

    /**
     * Validate uuid version.
     *
     * @throws InvalidUuidVersionException
     */
    public function validateVersion(int $value)
    {
        if (! in_array($value, [1, 3, 4, 5]))
        {
            throw new InvalidUuidVersionException();
        }
    }

}
