Edge ![](https://travis-ci.org/go1com/edge.svg?branch=master) [![Latest Stable Version](https://poser.pugx.org/go1/edge/v/stable.svg)](https://packagist.org/packages/go1/edge) [![License](https://poser.pugx.org/go1/edge/license)](https://packagist.org/packages/go1/edge)
====

Simple relationship controller.

```php
# Create the edge
$edge = new Edge($connection, 'edge', $type = 111);

# Instance if the database schema is not yet configured
$edge->install();

# Create connection from source to target
$edge->link(333, 555);
$edge->link(555, 333);
$edge->link(555, 999);
$edge->link(999, 555);

# Get connections
$edge->getTargetIds(555);        # = [333, 999]
$edge->getTargetIds([333, 555]); # = [333 => array(555), 555 => array(333, 999)]
$edge->getSourceIds(999);        # = [555]
$edge->getSourceIds([333, 999]); # = [333 => array(555), 999 => array(555)]

# Remove connection by source
$edge->clearUsingSource(555);

# Remove connection by target
$edge->clearUsingTarget(999);
```
