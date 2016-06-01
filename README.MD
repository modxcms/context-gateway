# ContextGateway

A MODX Gateway class that supports inherited Context Settings and 'aliases'. Utilizes advanced Context caching scheme by MODX Chief Architect [Jason "opengeek" Coward](https://github.com/opengeek), and routing class by [John "TheBoxer" Peca](https://github.com/TheBoxer)

## For Subfolders, NOT Domains

ContextGateway is for routing via subfolder, rather than domain. The unique feature of ContextGateway is nested "aliases" and Context Setting inheritance. If you need to use different domains for your Contexts, try [XRouting](http://modx.com/extras/package/xrouting).

## Special settings

The gateway class requires the use of a special Context Setting ```ctx_alias``` that acts as the uri bit for the Context on which it is set. The use of `ctx_parent` allows setting a parent Context, from which all settings will be inherited if they are not set on the current Context. You can thus "nest" Contexts and have them routed like this: "http://example.com/parent-context/child-context/resource-in-child-context/"

## Performance

This Plugin has been used for years by the MODX Team, on Production sites with over 1M monthly visitors.