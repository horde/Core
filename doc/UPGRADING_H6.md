# Namespace and no-namespace code

Horde Core H6 (WIP) currently ships with two implementations:

- The non-namespaced PEAR code inherited from H5-master, mostly PSR-0 compatible versions in /lib
- The namespaced, PSR-4 compatible versions in /src

This allows to take time, tackle individual subsystems incrementally as needed, without breaking existing code right now
There is simply no alternative option, resource wise

Maybe it makes sense to split off the refactored core into a separate package.

While some files are straight-forward ports, some subsystems are incompatible.

## The "Horde" Class

This class becomes Horde\Core\Horde.
Methods which rely on globals will be refactored to have parameter inputs.
Methods which no longer make sense that way will be omitted, but listed here.
### List of BC breaking changes (actual cases)

## Horde_PageOutput

Horde\Core\PageOutput may not emit side effects.
Methods may manipulate internal state or return strings or objects

Methods should not take mixed arguments if avoidable.

Cases where a method would take an object or a string producing an object should become two methods

As new code should strife to use the controller framework, directly outputting becomes undesirable
Also makes for easier testing

### List of BC breaking changes (actual cases)


## Horde_Config & Friends
### List of BC breaking changes (actual cases)

## Horde_Themes & Friends
### List of BC breaking changes (actual cases)

## Horde_Scripts & Friends
### List of BC breaking changes (actual cases)

## Horde_Registry & Friends
### List of BC breaking changes (actual cases)

## Horde_Exception subtypes in the Core package
### List of BC breaking changes (actual cases)

## Horde_Session & Friends
### List of BC breaking changes (actual cases)

## Horde_Shutdown Task Queue Handler & Friends
### List of BC breaking changes (actual cases)

## Horde_Themes & Friends
### List of BC breaking changes (actual cases)

## Horde_Menu, Topbar
### List of BC breaking changes (actual cases)

## Horde_Help
### List of BC breaking changes (actual cases)

## Horde_ErrorHandler
### List of BC breaking changes (actual cases)

## Horde_Deprecated
### List of BC breaking changes (actual cases)

This class will not be ported to H6. 