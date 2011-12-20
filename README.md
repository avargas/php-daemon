Simple PHP Daemon library
-------------------------

_Proof of concept_

Creating daemons and managing them easily using PHP.

Supports posix signals for ending child processes nicely.

Auto respawn, in case one of the childs exists and the parallel count is not met.


To run:

    $ git clone https://github.com/avargas/php-daemon
    $ cd php-daemon
    $ git submodule update --init --recursive
    $ php examples/simple_respawn.php

-----------------------
**TODO**:

* Daemon functionality (running by itself)
* Auto respawn on HUP
* Documentation
* Unit Testing