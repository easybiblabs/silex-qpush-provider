<?php

namespace EasyBib\QPush;

class ProviderRegistry extends \Uecode\Bundle\QPushBundle\Provider\ProviderRegistry
{
    /** @var string */
    private $suffix;

    public function __construct($suffix = '')
    {
        parent::__construct();
        $this->suffix = $suffix;
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return parent::get($name . $this->suffix);
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return parent::has($name . $this->suffix);
    }
}
