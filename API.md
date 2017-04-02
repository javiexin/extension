# Improved Extension Management

Improved Extension Management system for phpBB 3.2, extending the Extension Manager and Extension Metadata Manager, and replacing the ACP Extensions module.

Here are the descriptions of the included functionality and API changes.

## New Improved Extension Metadata Manager functionality

The Improved Extension Metadata Manager extends (ie inherits from) phpBB's Extension Metadata Manager.

* Eliminates dependencies on any external object, so the constructor is simplified
* Loads the metadata file just once, in the constructor, and keeps it; no need to reload multiple times
(in the current phpBB implementation, the file is reloaded each time there is a call to `get_metadata`)
* To keep consistency with prior versions, the constructor does not throw exceptions, but these are "saved" to be thrown on metadata access
* The `validate` method now accepts 'enable', 'dir', 'require\_php' and 'require\_phpbb', and calls the corresponding validation function
* All validation functions now throw exceptions; for functions that returned boolean false, there is a new default parameter to keep backward compatibility when needed
* The function `output_template_data` now throws an exception; should be removed, but due to inheritance, must be present, but SHOULD NOT be used
* **NEW 1.0.1**: All validation methods now allow the choice of throwing exceptions or returning boolean false on failure; the default parameter values are per the original behaviour

## New Improved Extension Manager functionality

The Improved Extension Manager decorates phpBB's Extension Manager.  To keep the type interface, it must inherits from phpBB's Extension Manager.

* The Extension Manager now encapsulates both the Metadata Manager and the Extension object (ext.php), 
making it redundant to access those elements directly; there are methods that proxy the equivalent from both;
there would be no need to have neither a metadata object nor an extension object outside of the control of the Extension Manager
but the getter methods for both metadata and extension are still available

```
	/**
	* Checks if an extension is enableable
	*
	* @param string $name The extension name
	* @return bool
	*/
	public function is_enableable($name)
```

```
	/**
	* Gets the metadata element for the extension with the given name
	*
	* @param string $name		The extension name
	* @param string $element	The extension metadata element: 'all' returns an array, 'name', 'version' and 'display-name' return string
	* @return string|array 		The metadata value for element, or null on error
	*/
	public function get_extension_metadata($name, $element = 'all')
```

* **Update 1.0.1**: metadata validation may throw exceptions or return boolean false on error

```
	/**
	* Validates the metadata element for the extension with the given name
	*
	* @param string $name		The extension name
	* @param string $element	The extension metadata element:
	*								'all' for display and enable validation returns an array, 'name', 'version' and 'display-name' return string
	*								'display' for name, type, license, version and authors
	*								'name', 'type', 'version', 'license' validate that field
	*								'enable', 'dir', 'authors', 'require_php', 'require_phpbb' validate the corresponding metadata parameter
	* @param bool $throw_exceptions if true, errors are reported as exceptions, otherwise, return false on error
	* @return bool 				True if valid, false or throws an exception if invalid
	* @throws \phpbb\extension\exception
	*/
	public function validate_extension_metadata($name, $element = 'display', $throw_exceptions = true)
```

```
	/**
	* Check the version and return the available updates (for an extension).
	*
	* @param string $ext_name The name of the extension to check
	* @param bool $force_update Ignores cached data. Defaults to false.
	* @param bool $force_cache Force the use of the cache. Override $force_update.
	* @param string $stability Force the stability (null by default).
	* @return string
	* @throws runtime_exception
	*/
	public function version_check_name($ext_name, $force_update = false, $force_cache = false, $stability = null)
```

* Metadata for extensions is cached in the Extension Manager, so only (up to) one Metadata Manager is created per extension, saving some file accesses
* Refactoring of the enable/disable/purge step methods to factor out common code to update the database ext table
* Changes is\_available to validate in the same way that is done for Metadata Manager, and it is made consistent with all\_available;
currently in phpBB is\_available and all\_available may give different results
* All state methods are now fully consistent (all\_enabled, all\_disabled, all\_configured, all\_available now use is\_enabled, is\_disabled, is\_configured, 
is\_available to generate the lists in a consistent way, no code replication)

* **NEW 1.0.3**: Added methods to check if an action is doable in an extension (depending on its current state)

```
	/**
	* Check to see if a given extension is available for enablement
	*
	* @param string $name Extension name to check
	* @return bool
	*/
	public function check_enableable($name)
```

```
	/**
	* Check to see if a given extension is available for disablement
	*
	* @param string $name Extension name to check
	* @return bool
	*/
	public function check_disableable($name)
```

```
	/**
	* Check to see if a given extension is available for purging
	*
	* @param string $name Extension name to check
	* @return bool
	*/
	public function check_purgeable($name)
```

## New Improved ACP Extension functionality

The Improved ACP Extension module replaces phpBB's ACP Extension module.  This is done in a 100% compatible way, keeping exactly all the functionality.
The module now uses a launcher module plus a dedicated Admin Controller that implement the module functionality.

* Compatible with 3.2.0, even though all the required events are not available, there is a workaround implemented, 
asking the user for a redirection to the new module; if accepted, the new module will be used; if not, the old one will continue functioning
* From 3.2.1 (expected), there will be additional core events that will allow for a full integration without the need of this redirect
* In both cases, an administrator may choose to use new module instead of the old one, but this is NOT forced from the extension
* In the event that this extension is disabled from within itself (may happen), it is automatically redirected to the official one
* The controller may be initialized either from the specific ACP module or from the event listener (3.2.1 onwards)

* Full refactoring of the ACP module, to avoid code duplication and for consistency: 
- enable/disable/delete\_data share most code; 
- lists of extensions are generated with the same function;
- version check is performed consistently, in a single function;
- all template variables and blocks are generated directly from the controller

* **NEW 1.0.1**: Refactoring extension related ACP templates (code sharing), now a single template file is used for all actions
on single or multiple extensions, with significant code reduction and simplification
* **NEW 1.0.1**: Include functionality to operate on multiple extensions at the same time (multi-enable, disable, delete\_data).
Allows to select multiple extensions from the extension list, and execute an action on all of them.  Extensions are executed in order.
No check is (yet) provided to allow only certain extensions for certain actions, but the right choice is validated before the action is executed.
Limitation: the Improved Extension Mgr cannot be disabled together with any other.
* **Update 1.0.2**: The Improved Extension Mgr may be disabled in a multi action; it is done last to avoid crash.
Trying to use multi action on an empty list now triggers an error. Minor bug fixes.

## Things to do (future functionality)

* Include code to solve or mitigate [**[ticket/15009]** Inconsistency all\_configured all\_available](https://github.com/phpbb/phpbb/pull/4644)
* Improve visuals and form behaviours in extension list for actions on multiple extensions
