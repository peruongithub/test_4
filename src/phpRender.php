<?php
namespace trident;

class phpRender
{
    /**
     * @param string $template
     * @param array $arguments
     * @param Triad
     * @return string
     */
    public function fetch($template, $arguments = [], $TRIAD = null)
    {
        if (empty($template)) {
            throw new \RuntimeException('Template not set');
        }
        if (!file_exists($template)) {
            throw new \RuntimeException(sprintf('File of template not exist. Given: %s', $template));
        }
        ob_start();
        ob_implicit_flush(false);
        if (!empty($arguments) && is_array($arguments)) {
            extract($arguments, EXTR_OVERWRITE);
        }

        require($template);

        return ob_get_clean();
    }
}
