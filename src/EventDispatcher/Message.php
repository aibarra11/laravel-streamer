<?php

namespace Prwnr\Streamer\EventDispatcher;

use Prwnr\Streamer\Concerns\HashableMessage;
use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\Contracts\StreamableMessage;

/**
 * Class Message.
 */
class Message implements StreamableMessage
{
    use HashableMessage;

    /**
     * @var array
     */
    protected $content;

    /**
     * @inheritdoc}
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Message constructor.
     *
     * @param array $meta
     * @param array $data
     */
    public function __construct(array $meta, $data)
    {
        $payload = [
            '_id'     => $meta['_id'] ?? '*',
            'type'    => $meta['type'] ?? Event::TYPE_EVENT,
            'version' => '1.3',
            'name'    => $meta['name'],
            'domain'  => $meta['domain'],
            'created' => time(),
            'data'    => $data,
        ];

        $this->content = $payload;
        $this->hashIt();
    }
}
