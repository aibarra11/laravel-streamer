<?php

namespace Prwnr\Streamer;

use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\StreamableMessage;
use Prwnr\Streamer\Contracts\Waitable;
use Prwnr\Streamer\Stream\Range;

/**
 * Class Stream.
 */
class Stream implements Waitable
{
    use ConnectsWithRedis;

    public const STREAM = 'STREAM';
    public const GROUPS = 'GROUPS';
    public const CREATE = 'CREATE';
    public const CONSUMERS = 'CONSUMERS';
    public const NEW_ENTRIES = '$';
    public const FROM_START = '0';

    /**
     * @var string
     */
    private $name;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getNewEntriesKey(): string
    {
        return self::NEW_ENTRIES;
    }

    /**
     * Stream constructor.
     *
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param StreamableMessage $message
     * @param string            $id
     *
     * @return mixed
     */
    public function add(StreamableMessage $message, string $id = '*')
    {
        return $this->redis()->xAdd($this->name, $id, $message->getContent());
    }

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function delete(string $id)
    {
        return $this->redis()->xDel($this->name, [$id]);
    }

    /**
     * @param string   $from
     * @param int|null $limit
     *
     * @return array
     */
    public function read(string $from = self::FROM_START, ?int $limit = null): array
    {
        if ($limit) {
            return $this->redis()->xRead([$this->name => $from], $limit);
        }

        return $this->redis()->xRead([$this->name => $from]);
    }

    /**
     * {@inheritdoc}
     */
    public function await(string $lastId = self::FROM_START, int $timeout = 0): ?array
    {
        return $this->redis()->xRead([$this->name => $lastId], null, $timeout);
    }

    /**
     * @param string $id
     */
    public function acknowledge(string $id): void
    {
        // When listening on Stream without a group we are not acknowledging any messages
    }

    /**
     * @param Range    $range
     * @param int|null $limit
     *
     * @return array
     */
    public function readRange(Range $range, ?int $limit = null): array
    {
        $method = 'xRANGE';
        $start = $range->getStart();
        $stop = $range->getStop();
        if ($range->getDirection() === Range::BACKWARD) {
            $method = 'xREVRANGE';
            $start = $range->getStop();
            $stop = $range->getStart();
        }

        if ($limit) {
            return $this->redis()->$method($this->name, $start, $stop, $limit);
        }

        return $this->redis()->$method($this->name, $start, $stop);
    }

    /**
     * @param  string  $name
     * @param  string  $from
     * @param  bool  $createStreamIfNotExists
     * @return bool
     */
    public function createGroup(string $name, string $from = self::FROM_START, bool $createStreamIfNotExists = true): bool
    {
        if ($createStreamIfNotExists) {
            return $this->redis()->xGroup(self::CREATE, $this->name, $name, $from, 'MKSTREAM');
        }

        return $this->redis()->xGroup(self::CREATE, $this->name, $name, $from);
    }

    /**
     * Return all pending messages from given group.
     * Optionally it can return pending message for single consumer.
     *
     * @param string      $group
     * @param null|string $consumer
     *
     * @return array
     */
    public function pending(string $group, ?string $consumer = null): array
    {
        $pending = $this->redis()->xPending($this->name, $group);
        $pendingCount = array_shift($pending);

        if ($consumer) {
            return $this->redis()->xPending($this->name, $group, Range::FIRST, Range::LAST, $pendingCount, $consumer);
        }

        return $this->redis()->xPending($this->name, $group, Range::FIRST, Range::LAST, $pendingCount);
    }

    /**
     * @return int
     */
    public function len(): int
    {
        return $this->redis()->xLen($this->name);
    }

    /**
     * @throws StreamNotFoundException
     *
     * @return array
     */
    public function info(): array
    {
        $result = $this->redis()->xInfo(self::STREAM, $this->name);
        if (!$result) {
            throw new StreamNotFoundException("No results for stream $this->name");
        }

        return $result;
    }

    /**
     * @throws StreamNotFoundException
     *
     * @return array
     */
    public function groups(): array
    {
        $result = $this->redis()->xInfo(self::GROUPS, $this->name);
        if (!$result) {
            throw new StreamNotFoundException("No results for stream $this->name");
        }

        return $result;
    }

    /**
     * @param string $group
     *
     * @throws StreamNotFoundException
     *
     * @return array
     */
    public function consumers(string $group): array
    {
        $result = $this->redis()->xInfo(self::CONSUMERS, $this->name, $group);
        if (!$result) {
            throw new StreamNotFoundException("No results for stream $this->name");
        }

        return $result;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function groupExists(string $name): bool
    {
        try {
            $groups = $this->groups();
        } catch (StreamNotFoundException $ex) {
            return false;
        }

        foreach ($groups as $group) {
            if ($group['name'] === $name) {
                return true;
            }
        }

        return false;
    }
}
