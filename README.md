# Horde Core Package

AI generated on 2025 april 20th.

The Horde Core package provides essential functionality for the Horde Framework, serving as the foundation for all Horde applications. It includes core services, base classes, and fundamental infrastructure components required by the Horde ecosystem.

## Key Features

- Application Registry and Management
- Authentication Framework
- Permission System
- Session Management
- Configuration Management
- Internationalization (i18n) Support
- Theme Management
- Core Utility Classes
- Basic Security Services

## Registry System

The Registry system is a central component of Horde Core that manages application registration, configuration, and inter-application communication. It consists of several key components:

### Core Registry (`Horde_Registry`)
- Handles application registration and management
- Manages application initialization and lifecycle
- Controls authentication settings
- Provides session flag management
- Defines error codes and view types
- Facilitates inter-application communication

### Registry API (`Horde_Registry_Api`)
- Manages API interfaces between applications
- Handles disabled methods and caching
- Controls API permissions
- Manages application links and relationships

### Registry Configuration (`Horde_Registry_Registryconfig`)
- Parses and manages registry configuration files
- Supports multiple configuration sources:
  - Main registry file (`registry.php`)
  - Registry directory files (`registry.d/*.php`)
  - Local registry file (`registry.local.php`)
  - Virtual host configurations
- Sets up application paths and resources
- Manages application status and interfaces
- Configures template, JavaScript, and theme paths
- Auto-detects Horde webroot

### NLS Configuration (`Horde_Registry_Nlsconfig`)
- Manages language and character set configurations
- Handles:
  - Character set mappings
  - Language aliases
  - Email character sets
  - RTL (Right-to-Left) language support
  - Multi-byte character support
  - Language validation

The registry system's modular design allows for:
- Flexible application registration
- Centralized configuration management
- Comprehensive language support
- Extensible API system
- Virtual host support
- Clear separation of concerns

## Installation

```bash
composer require horde/core
```

## Configuration

The core package requires proper configuration of the registry system. Main configuration files should be placed in:

- `config/registry.php` (main configuration)
- `config/registry.d/*.php` (additional configurations)
- `config/registry.local.php` (local overrides)

## Requirements

- PHP 7.0 or later
- Horde Framework dependencies
- Composer for package management

## License

This package is released under the LGPL-2.1 license. See LICENSE file for details.

## Additional Resources

- [Horde Project Website](https://www.horde.org)
- [Documentation](https://www.horde.org/docs)
- [API Documentation](https://www.horde.org/api) 
