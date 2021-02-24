<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Network\Bolt;

use Bolt\Bolt;
use Bolt\connection\StreamSocket;
use Ds\Vector;
use Exception;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\Network\AutoRoutedSession;

/**
 * @psalm-type ParsedUrl = array{fragment?: string, host: string, pass: string, path?: string, port?: int, query?: string, scheme?: string, user: string}
 */
final class BoltDriver implements DriverInterface
{
    /** @var ParsedUrl */
    private array $parsedUrl;
    private ?SessionInterface $session = null;
    private BoltConfig $injections;
    public const DEFAULT_TCP_PORT = 7687;
    private string $userAgent;

    /**
     * @param ParsedUrl $parsedUrl
     */
    public function __construct(array $parsedUrl, BoltConfig $injections, string $userAgent)
    {
        $this->parsedUrl = $parsedUrl;
        $this->injections = $injections;
        $this->userAgent = $userAgent;
    }

    /**
     * @throws Exception
     */
    public function aquireSession(FormatterInterface $formatter): SessionInterface
    {
        if ($this->session) {
            return $this->session;
        }

        try {
            $sock = new StreamSocket($this->parsedUrl['host'], $this->parsedUrl['port'] ?? self::DEFAULT_TCP_PORT);
            $options = $this->injections->getSslContextOptions();
            if ($options) {
                $sock->setSslContextOptions($options);
            }
            $bolt = new Bolt($sock);
            $bolt->init($this->userAgent, $this->parsedUrl['user'], $this->parsedUrl['pass']);
        } catch (Exception $e) {
            throw new Neo4jException(new Vector([new Neo4jError($e->getMessage(), '')]), $e);
        }

        if ($this->injections->hasAutoRouting()) {
            $basicSession = new BoltSession($this->parsedUrl, $bolt, new BasicFormatter(), $this->injections);
            $this->session = new AutoRoutedSession($formatter, $basicSession, $this->injections, $this->parsedUrl);
        } else {
            $this->session = new BoltSession($this->parsedUrl, $bolt, $formatter, $this->injections);
        }

        return $this->session;
    }
}
