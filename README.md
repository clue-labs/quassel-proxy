# clue/quassel-proxy

An experimental Quassel IRC proxy and protocol inspector.

A simple program that opens a local listening socket and forwards all data
to a real Quassel IRC core.

It also changes the probe request during the probe request in order to
**turn off encryption and compression**.

This is only useful for inspecting the low level protocol messages.

You should not use this ever.

## Usage

```bash
$ php proxy.php MYQUASSELCOREIP
```

Then connect your Quassel IRC client to `localhost:4242`.
