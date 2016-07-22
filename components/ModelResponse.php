<?php
/**
 * Created by PhpStorm.
 * User: peru
 * Date: 04.07.16
 * Time: 11:59
 */

namespace components;

class ModelResponse
{
    protected $message;
    protected $data;
    protected $action;
    protected $status;

    /**
     * ModelResponse constructor.
     * @param null $message
     * @param null $data
     * @param int $action
     * @param int $status
     */
    public function __construct($message = null, $data = null, $action = Action::GET, $status = Status::SUCCESS)
    {
        $this->setAction($action);
        $this->setMessage($message);
        $this->setData($data);
        $this->setStatus($status);
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setAction($action = Action::GET)
    {
        $this->action = $action;

        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status = Status::SUCCESS)
    {
        $this->status = $status;

        return $this;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message = null)
    {
        $this->message = $message;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data = null)
    {
        $this->data = $data;

        return $this;
    }

    public function toArray()
    {
        return [
            'message' => $this->message,
            'data' => $this->data,
            'action' => $this->action,
            'status' => $this->status,
        ];
    }
} 