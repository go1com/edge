<?php

namespace go1\edge;

use Symfony\Component\EventDispatcher\GenericEvent;

class EdgeEvent extends GenericEvent
{
    public function __construct(Edge $edge, array $link)
    {
        $this->subject = $edge;
        $this->arguments = $link;
    }
}
