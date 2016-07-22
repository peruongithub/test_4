<?php

namespace trident\HTTP;

trait httpMessage
{
    use HeaderTrait;

    /**
     * @var  The default protocol to use if it cannot be detected
     */
    public static $defaultProtocol = 'HTTP/1.1';
    protected $protocol;
    /**
     * @var  string
     */
    protected $body = '';

    /**
     * Gets or sets the HTTP protocol. If there is no current protocol set,
     * it will use the default set in static::$protocol
     *
     * @param   string $protocol Protocol to set to the request/response
     * @return  mixed
     */
    public function protocol($protocol = null)
    {
        if ($protocol === null) {
            if ($this->protocol) {
                return $this->protocol;
            } else {
                return $this->protocol = static::$defaultProtocol;
            }
        }
        if ($this->isLocked) {
            return $this;
        }
        $this->protocol = strtoupper($protocol);

        return $this;

    }

    /**
     * Gets or sets the body of the response
     * @param mixed
     * @return  mixed
     */
    public function body($content = null)
    {
        if ($content === null) {
            return $this->body;
        }
        if ($this->isLocked) {
            return $this;
        }
        if (null !== $content && !is_string($content) && !is_numeric($content) &&
            !is_callable(array($content, '__toString'))
        ) {
            throw new \UnexpectedValueException(
                'The Response content must be a string or object implementing __toString(), "'.
                gettype($content).'" given.'
            );
        }
        $this->body = (string)$content;

        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }

    /**
     * Returns the length of the body for use with
     * content header
     *
     * @return  integer
     */
    public function contentLength()
    {
        return strlen($this->body);
    }

    protected function headerString()
    {
        throw new \RuntimeException('This object must override method "headerString()".');
    }

} 