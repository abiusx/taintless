Taintless
=========

Taintless is a tool to break taint tracking and inference techniques used to protect applications against malicious user-input. 

The current version is tailored towards SQL injection, but can easily be modified to support all other types of protections and attacks.

The application supports 3 modes of operation:

1. Extract: extracts all useful fragments in a PHP application folder to be used in analysis and construction mode. Only possibly useful fragments are extracted.

2. Analysis: analyzes a PHP application and provides useful reports on how vulnerable the application is, even if protected by taint methods.

3. Construction: builds a string using fragments available in the application code, as best as it can. 

Taintless also includes a SQLmap tamper script which uses construction mode to modify payloads to only use strings available in the application code, hopefully bypassing positive taint inference.

For more details on taint protection methods and usages of Taintless, refer to the Black Hat 2014 presentation.
