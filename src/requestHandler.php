<?php
namespace trident;

interface requestHandler
{
    /**
     * Processes the request passed to it and returns the response from
     * the URI resource identified.
     *
     * This method must be implemented by all clients.
     *
     * @param   Request $request request to execute by client
     * @param   Response $response
     * @return  Response
     * @since   3.2.0
     */
    public function execute_request(Request $request, Response $response);
}
