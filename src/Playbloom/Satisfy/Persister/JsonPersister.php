<?php

namespace Playbloom\Satisfy\Persister;

use Playbloom\Satisfy\Model\Configuration;
use Playbloom\Satisfy\Model\PackageConstraint;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerAwareTrait;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Json definition file persister
 *
 * @author Ludovic Fleury <ludo.fleury@gmail.com>
 */
class JsonPersister implements PersisterInterface
{
    use SerializerAwareTrait;

    /** @var PersisterInterface */
    private $persister;

    /** @var string */
    private $satisClass;

    public function __construct(PersisterInterface $persister, SerializerInterface $serializer, string $satisClass)
    {
        $this->serializer = $serializer;
        $this->persister = $persister;
        $this->satisClass = $satisClass;
    }

    public function load(): Configuration
    {
        $jsonString = $this->persister->load();
        if ('' === trim($jsonString)) {
            throw new \RuntimeException('Satis file is empty.');
        }

        return $this->serializer->deserialize($jsonString, $this->satisClass, 'json');
    }

    public function flush($object): void
    {
        $jsonString = $this->serializer->serialize($object, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractObjectNormalizer::CALLBACKS => [
                'repositories' => [$this, 'normalizeRepositories'],
                'require' => [$this, 'normalizeRequire'],
            ],
        ]);
        $this->persister->flush($jsonString);
    }

    public function normalizeRepositories($repositories)
    {
        if ($repositories instanceof \ArrayIterator) {
            return array_values($repositories->getArrayCopy());
        }
    }

    /**
     * @param PackageConstraint[]|null $constraints
     *
     * @return string[]|null
     */
    public function normalizeRequire($constraints)
    {
        if (empty($constraints)) {
            return null;
        }
        $require = [];
        foreach ($constraints as $constraint) {
            $require[$constraint->getPackage()] = $constraint->getConstraint();
        }

        return $require;
    }
}
