<?php
/*******************************************************************************
 * 艺加商城
 *
 * (c)  2016  Gavin(田宇)  <tianyu_0723@hotmail.com>
 *
 ******************************************************************************/

use GuzzleHttp\Client as HttpClient;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Http
 */
class Http
{
    protected $client;

    protected $defaultOptions = [];


    /**
     * @return HttpClient
     */
    public function getClient()
    {
        if ( !$this->client instanceof HttpClient ) {
            $this->client = new HttpClient();
        }

        return $this->client;
    }

    /**
     * @param HttpClient $client
     *
     * @return $this
     */
    public function setClient( HttpClient $client )
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @param       $url
     * @param array $options
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function get( $url, $options = [] )
    {
        return $this->request($url, 'GET', [ 'query' => $options ]);
    }

    /**
     * @param       $url
     * @param array $options
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function post( $url, $options = [] )
    {
        $key = is_array($options) ? 'form_params' : 'body';

        return $this->request($url, 'POST', [ $key => $options ]);
    }

    /**
     * @param       $url
     * @param array $options
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function json( $url, $options = [] )
    {
        is_array($options) && $options = json_encode($options);

        return $this->request($url, 'POST', [ 'body' => $options, 'headers' => [ 'content-type' => 'application/json' ] ]);
    }

    /**
     * @param        $url
     * @param string $method
     * @param array  $options
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function request( $url, $method = 'GET', $options = [] )
    {
        $method = strtoupper($method);

        $options = array_merge($this->defaultOptions, $options);

        $response = $this->getClient()->request($method, $url, $options);

        return $response;
    }

    /**
     * 解析json 返回数组
     *
     * @param $body
     *
     * @return bool|mixed
     */
    public function parseJSON($body)
    {
        if ($body instanceof ResponseInterface) {
            $body = $body->getBody();
        }

        if (empty($body)) {
            return false;
        }

        $contents = json_decode($body, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
//            throw new HttpException('Failed to parse JSON: '.json_last_error_msg());
            return false;
        }

        return $contents;
    }

}