<img src="thegamepanel-banner.png" alt="The Game Panel">

# The Game Panel

## Open-source game server management

![GitHub License](https://img.shields.io/github/license/thegamepanel/panel)
![PHP Version](https://img.shields.io/badge/php-%3E%3D8.5-777BB4?logo=php&logoColor=white)
[![codecov](https://codecov.io/github/thegamepanel/panel/graph/badge.svg?token=CS2F99WQSJ)](https://codecov.io/github/thegamepanel/panel)

Everything lives here: the `Engine\` layer providing the foundational architecture, the modules built on top of it,
the worker entry point, and the build that produces the single binary. Third-party modules extend the panel through
the same module system the first-party features are built on.

The panel ships as one binary running FrankenPHP in worker mode with Caddy underneath. The frontend is server-rendered
HTML with hypermedia interactions.

> [!NOTE]
> Pre-1.0 and under active development. What exists today is the dependency injection container, the config component
> (TOML with env interpolation), the database layer (connections, query builder and schema), and the value casting
> helpers. The HTTP layer, boot pipeline, module system, and the concepts around contexts, roles and permissions are
> all roadmap rather than shipped.
