<?php
/**
 *
 * Error Codes: 401 - 407
 */
namespace Nibynool\FitbitInterfaceBundle\Fitbit;

use SimpleXMLElement;
use OAuth\OAuth1\Service\Fitbit as ServiceInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Nibynool\FitbitInterfaceBundle\Fitbit\Exception as FBException;

/**
 * Class EndpointGateway
 *
 * @package Nibynool\FitbitInterfaceBundle\Fitbit
 *
 * @since 0.1.0
 */
class EndpointGateway
{
    /**
     * @var ServiceInterface
     */
    protected $service;
    /**
     * @var string
     */
    protected $responseFormat;
    /**
     * @var string
     */
    protected $userID;
	/**
	 * @var array $configuration
	 */
	protected $configuration;
	/**
	 * @var Stopwatch
	 */
	protected $stopwatch;

	public function __construct($configuration, $stopwatch)
	{
		$this->configuration = $configuration;
		$this->stopwatch = $stopwatch;
	}

    /**
     * Set Fitbit service
     *
     * @access public
     *
     * @param ServiceInterface $service
     * @return self
     */
    public function setService(ServiceInterface $service)
    {
        $this->service = $service;
        return $this;
    }

    /**
     * Set response format.
     * 
     * @access public
     *
     * @param string $format
     * @return self
     */
    public function setResponseFormat($format)
    {
        $this->responseFormat = $format;
        return $this;
    }

    /**
     * Set Fitbit user ids.
     *
     * @access public
     *
     * @param string $id
     * @return self
     */
    public function setUserID($id)
    {
        $this->userID = $id;
        return $this;
    }

    /**
     * Make an API request
     *
     * @access protected
     * @version 0.5.3
     *
     * @param string $resource Endpoint after '.../1/'
     * @param string $method ('GET', 'POST', 'PUT', 'DELETE')
     * @param array $body Request parameters
     * @param array $extraHeaders Additional custom headers
     * @throws FBException
     * @return SimpleXMLElement|object The result as an object or SimpleXMLElement
     */
    protected function makeApiRequest($resource, $method = 'GET', $body = array(), $extraHeaders = array())
    {
	    /** @var Stopwatch $timer */
	    $timer = $this->stopwatch;

	    $timer->start('API Request', 'Fitbit_API');

        $path = $resource . '.' . $this->responseFormat;

        if ($method == 'GET' && $body) {
            $path .= '?' . http_build_query($body);
            $body = array();
        }

	    $requestStart = microtime(true);
	    try
	    {
            $response = $this->service->request($path, $method, $body, $extraHeaders);
	    }
	    catch (\Exception $e)
	    {
		    try
		    {
			    $requestEnd = microtime(true);
			    $logger = RequestLogger::getInstance();
			    $logger::logApiCall(
				    array(
					    'path'    => $path,
					    'method'  => $method,
					    'body'    => $body,
					    'headers' =>$extraHeaders
				    ),
				    ($requestEnd - $requestStart),
				    null,
				    $e
			    );
		    }
		    catch (\Exception $e)
		    {}
		    $timer->stop('API Request');
		    throw new FBException('The service request failed.', 401, $e);
	    }
	    $requestEnd = microtime(true);

        try
        {
	        $response = $this->parseResponse($response);
        }
        catch (\Exception $e)
	    {
		    $timer->stop('API Request');
		    throw new FBException('The response from Fitbit could not be interpreted.', 402, $e);
	    }

	    try
	    {
			$logger = RequestLogger::getInstance();
		    $logger::logApiCall(
			    array(
				    'path'    => $path,
		            'method'  => $method,
				    'body'    => $body,
				    'headers' =>$extraHeaders
			    ),
			    ($requestEnd - $requestStart),
			    $response,
			    null
		    );
	    }
	    catch (\Exception $e)
	    {}

	    $timer->stop('API Request');
	    return $response;
    }

    /**
     * Parse json or XML response.
     *
     * @access private
     *
     * @param string $response
     * @throws FBException
     * @return mixed stdClass for json response, SimpleXMLElement for XML response.
     */
    private function parseResponse($response)
    {
        if ($this->responseFormat == 'json')
        {
	        try
	        {
		        $response = json_decode($response);
	        }
	        catch (\Exception $e)
	        {
		        throw new FBException('Could not decode JSON response.', 403);
	        }
        }
        elseif ($this->responseFormat == 'xml')
        {
	        try
	        {
		        $response = simplexml_load_string($response);
	        }
	        catch (\Exception $e)
	        {
		        throw new FBException('Could not decode XML response.', 404);
	        }
        }
		else throw new FBException('Could not handle a response format of '.$this->responseFormat, 405);
	    return $response;
    }

    /**
     * Get CLIENT+VIEWER and CLIENT rate limiting quota status
     *
     * @access public
     *
     * @throws FBException
     * @return RateLimiting
     */
    public function getRateLimit()
    {
	    /** @var Stopwatch $timer */
	    $timer = $this->stopwatch;
	    $timer->start('API Rate Limit Request', 'Fitbit_API');
	    try
	    {
            $clientAndUser = $this->makeApiRequest('account/clientAndViewerRateLimitStatus');
            $client        = $this->makeApiRequest('account/clientRateLimitStatus');
	    }
	    catch (\Exception $e)
	    {
		    $timer->stop('API Rate Limit Request');
		    return false;
	    }

	    try
	    {
	        $rateLimiting = new RateLimiting(
	            $clientAndUser->rateLimitStatus->remainingHits,
	            $client->rateLimitStatus->remainingHits,
	            new \DateTime($clientAndUser->rateLimitStatus->resetTime),
	            new \DateTime($client->rateLimitStatus->resetTime),
	            $clientAndUser->rateLimitStatus->hourlyLimit,
	            $client->rateLimitStatus->hourlyLimit
	        );
		    $timer->stop('API Rate Limit Request');
		    return $rateLimiting;
	    }
	    catch (\Exception $e)
	    {
		    $timer->stop('API Rate Limit Request');
		    throw new FBException('Could not create the rate limiting object.', 407, $e);
	    }
    }
}
