<?php

namespace PrevailExcel\Fincra\Traits;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use PrevailExcel\Fincra\Exceptions\FincraException;

trait HandlesApiRequests
{
    /**
     * Make HTTP Request
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws FincraException
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        try {
            $options = [];
            
            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, $endpoint, $options);
            
            $body = json_decode($response->getBody()->getContents(), true);
            
            if (!$body['status']) {
                throw new FincraException($body['message'] ?? 'Request failed');
            }
            
            return $body;
            
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody()->getContents(), true);
            
            throw new FincraException(
                $body['message'] ?? 'Client error occurred',
                $response->getStatusCode()
            );
            
        } catch (ServerException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody()->getContents(), true);
            
            throw new FincraException(
                $body['message'] ?? 'Server error occurred',
                $response->getStatusCode()
            );
            
        } catch (\Exception $e) {
            throw new FincraException($e->getMessage());
        }
    }
}