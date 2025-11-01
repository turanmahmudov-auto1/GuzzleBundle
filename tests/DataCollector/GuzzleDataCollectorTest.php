<?php

namespace Playbloom\Bundle\GuzzleBundle\Tests\DataCollector;

use Playbloom\Bundle\GuzzleBundle\DataCollector\GuzzleDataCollector;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Guzzle DataCollector unit test
 *
 * @author Ludovic Fleury <ludo.fleury@gmail.com>
 */
class GuzzleDataCollectorTest extends \PHPUnit\Framework\TestCase
{
    public function testGetName()
    {
        $guzzleDataCollector = $this->createGuzzleCollector();

        $this->assertEquals($guzzleDataCollector->getName(), 'guzzle');
    }

    /**
     * Test an empty GuzzleDataCollector
     */
    public function testCollectEmpty()
    {
        // test an empty collector
        /** @var GuzzleDataCollector $guzzleDataCollector */
        $guzzleDataCollector = $this->createGuzzleCollector();

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->getMock();
        $response = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')
            ->getMock();
        /** @var GuzzleDataCollector $guzzleDataCollector */
        $guzzleDataCollector->collect($request, $response);

        $this->assertEquals($guzzleDataCollector->getCalls(), array());
        $this->assertEquals($guzzleDataCollector->countErrors(), 0);
        $this->assertEquals($guzzleDataCollector->getMethods(), array());
        $this->assertEquals($guzzleDataCollector->getTotalTime(), 0);
    }

    /**
     * Test a DataCollector containing one valid call
     *
     * HTTP response code 100+ and 200+
     */
    public function testCollectValidCall()
    {
        // test a regular call
        $callInfos = array('connect_time' => 15, 'total_time' => 150);
        $callUrlQuery = $this->stubQuery(array('foo' => 'bar'));
        $callRequest = $this->stubRequest('get', 'http', 'test.local', '/', $callUrlQuery);
        $callResponse = $this->stubResponse(200, 'OK', 'Hello world');
        $call = $this->stubCall($callRequest, $callResponse, $callInfos);
        /** @var GuzzleDataCollector $guzzleDataCollector */
        $guzzleDataCollector = $this->createGuzzleCollector(array($call));

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();
        $response = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')
            ->disableOriginalConstructor()
            ->getMock();
        $guzzleDataCollector->collect($request, $response);

        $this->assertCount(1, $guzzleDataCollector->getCalls());
        $this->assertEquals($guzzleDataCollector->countErrors(), 0);
        $this->assertEquals($guzzleDataCollector->getMethods(), array('get' => 1));
        $this->assertEquals($guzzleDataCollector->getTotalTime(), 0.15);

        $calls = $guzzleDataCollector->getCalls();
        $this->assertEquals(
            $calls[0],
            array(
                'request' => array(
                    'headers' => [],
                    'method'  => 'get',
                    'scheme'  => 'http',
                    'host'    => 'test.local',
                    'port'    => 80,
                    'path'    => '/',
                    'query'   => ['foo' => 'bar'],
                    'body'    => ''
                ),
                'response' => array(
                    'statusCode'   => 200,
                    'reasonPhrase' => 'OK',
                    'headers'      => [],
                    'body'         => 'Hello world',
                ),
                'time' => array(
                    'total'      => 0.15,
                    'connection' => 0.015
                ),
                'error' => false
            )
        );
    }

    /**
     * Test a DataCollector containing one faulty call
     *
     * HTTP response code 400+ & 500+
     */
    public function testCollectErrorCall()
    {
        // test an error call
        $callInfos = array('connect_time' => 15, 'total_time' => 150);
        $callUrlQuery = $this->stubQuery(array('foo' => 'bar'));
        $callRequest = $this->stubRequest('post', 'http', 'test.local', '/', $callUrlQuery);
        $callResponse = $this->stubResponse(404, 'Not found', 'Oops');
        $call = $this->stubCall($callRequest, $callResponse, $callInfos);
        /** @var GuzzleDataCollector $guzzleDataCollector */
        $guzzleDataCollector = $this->createGuzzleCollector(array($call));

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->getMock();
        $response = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')
            ->getMock();
        $guzzleDataCollector->collect($request, $response);

        $this->assertCount(1, $guzzleDataCollector->getCalls());
        $this->assertEquals($guzzleDataCollector->countErrors(), 1);
        $this->assertEquals($guzzleDataCollector->getMethods(), array('post' => 1));
        $this->assertEquals($guzzleDataCollector->getTotalTime(), 0.15);

        $calls = $guzzleDataCollector->getCalls();
        $this->assertEquals(
            $calls[0],
            array(
                'request' => array(
                    'headers' => [],
                    'method'  => 'post',
                    'scheme'  => 'http',
                    'host'    => 'test.local',
                    'port'    => 80,
                    'path'    => '/',
                    'query'   => ['foo' => 'bar'],
                    'body'    => '',
                ),
                'response' => array(
                    'statusCode'   => 404,
                    'reasonPhrase' => 'Not found',
                    'headers'      => [],
                    'body'         => 'Oops',
                ),
                'time' => array(
                    'total'      => 0.15,
                    'connection' => 0.015
                ),
                'error' => true
            )
        );
    }

    /**
     * Test a DataCollector containing one call with request content
     *
     * The request has a body content like POST or PUT
     * In this case the call contains a Guzzle\Http\Message\EntityEnclosingRequestInterface
     * which should be sanitized/casted as a string
     */
    public function testCollectBodyRequestCall()
    {
        $callInfos = array('connect_time' => 15, 'total_time' => 150);
        $callUrlQuery = $this->stubQuery(array('foo' => 'bar'));
        $callRequest = $this->stubRequest('post', 'http', 'test.local', '/', $callUrlQuery, 'Request body string');
        $callResponse = $this->stubResponse(201, 'Created', '');
        $call = $this->stubCall($callRequest, $callResponse, $callInfos);
        $guzzleDataCollector = $this->createGuzzleCollector(array($call));

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->getMock();
        $response = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')
            ->getMock();
        $guzzleDataCollector->collect($request, $response);

        $this->assertEquals(count($guzzleDataCollector->getCalls()), 1);
        $this->assertEquals($guzzleDataCollector->countErrors(), 0);
        $this->assertEquals($guzzleDataCollector->getMethods(), array('post' => 1));
        $this->assertEquals($guzzleDataCollector->getTotalTime(), 0.15);

        $calls = $guzzleDataCollector->getCalls();
        $this->assertEquals(
            $calls[0],
            array(
                'request' => array(
                    'headers' => [],
                    'method'  => 'post',
                    'scheme'  => 'http',
                    'host'    => 'test.local',
                    'port'    => 80,
                    'path'    => '/',
                    'query'   => ['foo' => 'bar'],
                    'body'    => 'Request body string',
                ),
                'response' => array(
                    'statusCode'   => 201,
                    'reasonPhrase' => 'Created',
                    'headers'      => [],
                    'body'         => '',
                ),
                'time' => array(
                    'total'      => 0.15,
                    'connection' => 0.015
                ),
                'error' => false
            )
        );
    }

    protected function createGuzzleCollector(array $calls = array())
    {
        return new GuzzleDataCollector(new HistoryPluginStub($calls));
    }

    protected function stubCall($request, $response, array $info)
    {
        return [
            'request' => $request,
            'response' => $response,
            'transfer_stats' => $this->createTransferStats($info)
        ];
    }

    protected function createTransferStats(array $info)
    {
        $stats = $this->createMock(\GuzzleHttp\TransferStats::class);
        $stats->method('getTransferTime')->willReturn($info['total_time'] / 1000);
        $stats->method('getHandlerStats')->willReturn([
            'connect_time' => $info['connect_time'] / 1000
        ]);
        return $stats;
    }

    protected function stubQuery(array $query)
    {
        return http_build_query($query);
    }

    protected function stubRequest($method, $scheme, $host, $path, $query, $body = null)
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getScheme')->willReturn($scheme);
        $uri->method('getHost')->willReturn($host);
        $uri->method('getPort')->willReturn(80);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getQuery')->willReturn($query);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaders')->willReturn([]);

        if ($body !== null) {
            $bodyStream = $this->createMock(StreamInterface::class);
            $bodyStream->method('__toString')->willReturn($body);
            $request->method('getBody')->willReturn($bodyStream);
        } else {
            $bodyStream = $this->createMock(StreamInterface::class);
            $bodyStream->method('__toString')->willReturn('');
            $request->method('getBody')->willReturn($bodyStream);
        }

        return $request;
    }

    protected function stubResponse($code, $reason, $body)
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($code);
        $response->method('getReasonPhrase')->willReturn($reason);
        $response->method('getHeaders')->willReturn([]);

        $bodyStream = $this->createMock(StreamInterface::class);
        $bodyStream->method('__toString')->willReturn($body);
        $response->method('getBody')->willReturn($bodyStream);

        return $response;
    }
}
