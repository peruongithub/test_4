<?php
namespace components;

class ModelMultiResponse extends ModelResponse
{
    protected $messages = [];
    protected $responses = [];

    public function addResponse(ModelResponse $response)
    {
        if ($response instanceof ModelMultiResponse) {
            $this->responses += $response->getResponses();
            //convert ModelMultiResponse to ModelResponse
            $response = new ModelResponse(
                $response->getMessage(),
                $response->getData(),
                $response->getAction(),
                $response->getStatus()
            );
        }
        $this->responses[] = $response;

        return $this;
    }

    public function getResponses()
    {
        return $this->responses;
    }

    public function toArray()
    {
        $responses = [];
        foreach ($this->responses as $response) {
            $responses[] = $response->toArray();
        }

        return [
            'message' => $this->message,
            'responses' => $responses,
            'data' => $this->data,
            'action' => $this->action,
            'status' => $this->status,
        ];
    }
} 