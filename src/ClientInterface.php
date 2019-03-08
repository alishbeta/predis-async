<?php

namespace Predis\Async;

use Predis\Async\Connection\ConnectionInterface;
use Predis\Async\Transaction\MultiExec;
use Predis\Command\CommandInterface;
use Predis\Configuration\OptionsInterface;
use Predis\Profile\ProfileInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

/**
 *  Client class used for connecting and executing commands on Redis.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 *
 * @method PromiseInterface|int    del(array $keys)
 * @method PromiseInterface|string dump($key)
 * @method PromiseInterface|int    exists($key)
 * @method PromiseInterface|int    expire($key, $seconds)
 * @method PromiseInterface|int    expireat($key, $timestamp)
 * @method PromiseInterface|array  keys($pattern)
 * @method PromiseInterface|int    move($key, $db)
 * @method PromiseInterface|mixed  object($subcommand, $key)
 * @method PromiseInterface|int    persist($key)
 * @method PromiseInterface|int    pexpire($key, $milliseconds)
 * @method PromiseInterface|int    pexpireat($key, $timestamp)
 * @method PromiseInterface|int    pttl($key)
 * @method PromiseInterface|string randomkey()
 * @method PromiseInterface|mixed  rename($key, $target)
 * @method PromiseInterface|int    renamenx($key, $target)
 * @method PromiseInterface|array  scan($cursor, array $options = null)
 * @method PromiseInterface|array  sort($key, array $options = null)
 * @method PromiseInterface|int    ttl($key)
 * @method PromiseInterface|mixed  type($key)
 * @method PromiseInterface|int    append($key, $value)
 * @method PromiseInterface|int    bitcount($key, $start = null, $end = null)
 * @method PromiseInterface|int    bitop($operation, $destkey, $key)
 * @method PromiseInterface|array  bitfield($key, $subcommand, ...$subcommandArg)
 * @method PromiseInterface|int    decr($key)
 * @method PromiseInterface|int    decrby($key, $decrement)
 * @method PromiseInterface|string get($key)
 * @method PromiseInterface|int    getbit($key, $offset)
 * @method PromiseInterface|string getrange($key, $start, $end)
 * @method PromiseInterface|string getset($key, $value)
 * @method PromiseInterface|int    incr($key)
 * @method PromiseInterface|int    incrby($key, $increment)
 * @method PromiseInterface|string incrbyfloat($key, $increment)
 * @method PromiseInterface|array  mget(array $keys)
 * @method PromiseInterface|mixed  mset(array $dictionary)
 * @method PromiseInterface|int    msetnx(array $dictionary)
 * @method PromiseInterface|mixed  psetex($key, $milliseconds, $value)
 * @method PromiseInterface|mixed  set($key, $value, $expireResolution = null, $expireTTL = null, $flag = null)
 * @method PromiseInterface|int    setbit($key, $offset, $value)
 * @method PromiseInterface|int    setex($key, $seconds, $value)
 * @method PromiseInterface|int    setnx($key, $value)
 * @method PromiseInterface|int    setrange($key, $offset, $value)
 * @method PromiseInterface|int    strlen($key)
 * @method PromiseInterface|int    hdel($key, array $fields)
 * @method PromiseInterface|int    hexists($key, $field)
 * @method PromiseInterface|string hget($key, $field)
 * @method PromiseInterface|array  hgetall($key)
 * @method PromiseInterface|int    hincrby($key, $field, $increment)
 * @method PromiseInterface|string hincrbyfloat($key, $field, $increment)
 * @method PromiseInterface|array  hkeys($key)
 * @method PromiseInterface|int    hlen($key)
 * @method PromiseInterface|array  hmget($key, array $fields)
 * @method PromiseInterface|mixed  hmset($key, array $dictionary)
 * @method PromiseInterface|array  hscan($key, $cursor, array $options = null)
 * @method PromiseInterface|int    hset($key, $field, $value)
 * @method PromiseInterface|int    hsetnx($key, $field, $value)
 * @method PromiseInterface|array  hvals($key)
 * @method PromiseInterface|int    hstrlen($key, $field)
 * @method PromiseInterface|array  blpop(array $keys, $timeout)
 * @method PromiseInterface|array  brpop(array $keys, $timeout)
 * @method PromiseInterface|array  brpoplpush($source, $destination, $timeout)
 * @method PromiseInterface|string lindex($key, $index)
 * @method PromiseInterface|int    linsert($key, $whence, $pivot, $value)
 * @method PromiseInterface|int    llen($key)
 * @method PromiseInterface|string lpop($key)
 * @method PromiseInterface|int    lpush($key, array $values)
 * @method PromiseInterface|int    lpushx($key, $value)
 * @method PromiseInterface|array  lrange($key, $start, $stop)
 * @method PromiseInterface|int    lrem($key, $count, $value)
 * @method PromiseInterface|mixed  lset($key, $index, $value)
 * @method PromiseInterface|mixed  ltrim($key, $start, $stop)
 * @method PromiseInterface|string rpop($key)
 * @method PromiseInterface|string rpoplpush($source, $destination)
 * @method PromiseInterface|int    rpush($key, array $values)
 * @method PromiseInterface|int    rpushx($key, $value)
 * @method PromiseInterface|int    sadd($key, array $members)
 * @method PromiseInterface|int    scard($key)
 * @method PromiseInterface|array  sdiff(array $keys)
 * @method PromiseInterface|int    sdiffstore($destination, array $keys)
 * @method PromiseInterface|array  sinter(array $keys)
 * @method PromiseInterface|int    sinterstore($destination, array $keys)
 * @method PromiseInterface|int    sismember($key, $member)
 * @method PromiseInterface|array  smembers($key)
 * @method PromiseInterface|int    smove($source, $destination, $member)
 * @method PromiseInterface|string spop($key, $count = null)
 * @method PromiseInterface|string srandmember($key, $count = null)
 * @method PromiseInterface|int    srem($key, $member)
 * @method PromiseInterface|array  sscan($key, $cursor, array $options = null)
 * @method PromiseInterface|array  sunion(array $keys)
 * @method PromiseInterface|int    sunionstore($destination, array $keys)
 * @method PromiseInterface|int    zadd($key, array $membersAndScoresDictionary)
 * @method PromiseInterface|int    zcard($key)
 * @method PromiseInterface|string zcount($key, $min, $max)
 * @method PromiseInterface|string zincrby($key, $increment, $member)
 * @method PromiseInterface|int    zinterstore($destination, array $keys, array $options = null)
 * @method PromiseInterface|array  zrange($key, $start, $stop, array $options = null)
 * @method PromiseInterface|array  zrangebyscore($key, $min, $max, array $options = null)
 * @method PromiseInterface|int    zrank($key, $member)
 * @method PromiseInterface|int    zrem($key, $member)
 * @method PromiseInterface|int    zremrangebyrank($key, $start, $stop)
 * @method PromiseInterface|int    zremrangebyscore($key, $min, $max)
 * @method PromiseInterface|array  zrevrange($key, $start, $stop, array $options = null)
 * @method PromiseInterface|array  zrevrangebyscore($key, $max, $min, array $options = null)
 * @method PromiseInterface|int    zrevrank($key, $member)
 * @method PromiseInterface|int    zunionstore($destination, array $keys, array $options = null)
 * @method PromiseInterface|string zscore($key, $member)
 * @method PromiseInterface|array  zscan($key, $cursor, array $options = null)
 * @method PromiseInterface|array  zrangebylex($key, $start, $stop, array $options = null)
 * @method PromiseInterface|array  zrevrangebylex($key, $start, $stop, array $options = null)
 * @method PromiseInterface|int    zremrangebylex($key, $min, $max)
 * @method PromiseInterface|int    zlexcount($key, $min, $max)
 * @method PromiseInterface|int    pfadd($key, array $elements)
 * @method PromiseInterface|mixed  pfmerge($destinationKey, array $sourceKeys)
 * @method PromiseInterface|int    pfcount(array $keys)
 * @method PromiseInterface|mixed  pubsub($subcommand, $argument)
 * @method PromiseInterface|int    publish($channel, $message)
 * @method PromiseInterface|mixed  discard()
 * @method PromiseInterface|array  exec()
 * @method PromiseInterface|mixed  multi()
 * @method PromiseInterface|mixed  unwatch()
 * @method PromiseInterface|mixed  watch($key)
 * @method PromiseInterface|mixed  eval($script, $numkeys, ...$keyOrArgs)
 * @method PromiseInterface|mixed  evalsha($script, $numkeys, ...$keyOrArgs)
 * @method PromiseInterface|mixed  script($subcommand, $argument = null)
 * @method PromiseInterface|mixed  auth($password)
 * @method PromiseInterface|string echo ($message)
 * @method PromiseInterface|mixed  ping($message = null)
 * @method PromiseInterface|mixed  select($database)
 * @method PromiseInterface|mixed  bgrewriteaof()
 * @method PromiseInterface|mixed  bgsave()
 * @method PromiseInterface|mixed  client($subcommand, $argument = null)
 * @method PromiseInterface|mixed  config($subcommand, $argument = null)
 * @method PromiseInterface|int    dbsize()
 * @method PromiseInterface|mixed  flushall()
 * @method PromiseInterface|mixed  flushdb()
 * @method PromiseInterface|array  info($section = null)
 * @method PromiseInterface|int    lastsave()
 * @method PromiseInterface|mixed  save()
 * @method PromiseInterface|mixed  slaveof($host, $port)
 * @method PromiseInterface|mixed  slowlog($subcommand, $argument = null)
 * @method PromiseInterface|array  time()
 * @method PromiseInterface|array  command()
 * @method PromiseInterface|int    geoadd($key, $longitude, $latitude, $member)
 * @method PromiseInterface|array  geohash($key, array $members)
 * @method PromiseInterface|array  geopos($key, array $members)
 * @method PromiseInterface|string geodist($key, $member1, $member2, $unit = null)
 * @method PromiseInterface|array  georadius($key, $longitude, $latitude, $radius, $unit, array $options = null)
 * @method PromiseInterface|array  georadiusbymember($key, $member, $radius, $unit, array $options = null)
 */
interface ClientInterface
{
    /**
     * Returns the client options specified upon initialization.
     *
     * @return OptionsInterface
     */
    public function getOptions();

    /**
     * Returns the server profile used by the client.
     *
     * @return ProfileInterface
     */
    public function getProfile();

    /**
     * Returns the underlying event loop.
     *
     * @return LoopInterface
     */
    public function getEventLoop();

    /**
     * Opens the connection to the server.
     *
     * @return PromiseInterface
     */
    public function connect();

    /**
     * Closes the underlying connection from the server.
     *
     * @return PromiseInterface
     */
    public function disconnect();

    /**
     * Returns the current state of the underlying connection.
     *
     * @return bool
     */
    public function isConnected();

    /**
     * Returns the underlying connection instance.
     *
     * @return ConnectionInterface
     */
    public function getConnection();

    /**
     * Creates a new instance of the specified Redis command.
     *
     * @param string $method Command ID.
     * @param array $arguments Arguments for the command.
     *
     * @return CommandInterface
     */
    public function createCommand($method, $arguments = []);

    /**
     * Executes the specified Redis command.
     *
     * @param CommandInterface $command Command instance.
     * @return PromiseInterface
     */
    public function executeCommand(CommandInterface $command);

    /**
     * Creates a new transaction context.
     *
     * @return MultiExec
     */
    public function transaction();

    /**
     * Creates a new monitor consumer.
     *
     * @param callable $callback Callback invoked on each payload message.
     * @param bool $autostart Flag indicating if the consumer should be auto-started.
     *
     * @return Monitor\Consumer
     */
    public function monitor(callable $callback, $autostart = true);

    /**
     * Creates a new pub/sub consumer.
     *
     * @param mixed $channels List of channels for subscription.
     * @param callable $callback Callback invoked on each payload message.
     *
     * @return PubSub\Consumer
     */
    public function pubSubLoop($channels, callable $callback);

    /**
     * {@inheritdoc}
     */
    public function pipeline();
}
