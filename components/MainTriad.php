<?php
namespace components;


use trident\Triad;

class MainTriad extends Triad
{
    public function index()
    {
        return [
            'htmlTitle' => 'Simple url minimization',
            'mainContent' => 'Only for demonstration'
        ];
    }

    public function init($options = null)
    {
        parent::init($options);
        $this->defaultConfigForActions['template'] = './data/tpl/layout.tpl.php';
    }
}