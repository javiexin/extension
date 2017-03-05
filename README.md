# Improved Extension Management

Improved Extension Management system for phpBB 3.2, implementing a number of proposed core changes and additional functionalities.

Extends the Extension Manager and Extension Metadata Manager, and replaces the ACP Extensions module.

Uses service decoration for Extension Manager, service inheritance for Extension Metadata Manager,
and a combination of listener, module and admin controller for ACP module.

It replaces the core extension management service with these improved services, 100% compatible and extended.

See [API.md](API.md) for a description of the new object methods and changes.

See [CORE.md](CORE.md) for a list of proposed core changes included here.

## Installation

Copy the extension to phpBB/ext/javiexin/extension

Go to "ACP" > "Customise" > "Extensions" and enable the "Improved Extension Management" extension.

## License

[GPLv2](license.txt)
