# `1.2.0` (unreleased)

* Add a logger and enable `-v` and `-vv` options
* Add the `--no-networks` and `--no-ports` options to avoid rendering networks and ports
* Add the `--background` option to set the graph's background color
* Versions correctly merged and checked

# `1.1.0`

* Display `depends_on` conditions
* Handle conditions in `depends_on`
* Automatically load override file if it exists or ignore it using `--ignore-override`

# `1.0.0`

* Avoid duplicating edges when there is multiple extended services
* Display extended services as components with inverted arrows
* Display services as components
* Display volumes as folders
* Display ports as circles
* Display networks as pentagon
* Display service links as plain arrows
* Display service dependencies as dotted arrows
* Display volume links as dashed arrows
* Display external resources as grayed items
* Render graph as PNG (`image` renderer)
* Render graph as dot file (`dot` renderer)
* Open graph as PNG image (`display` renderer)
