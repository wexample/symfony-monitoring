`symfony-monitoring` follows what goes wrong in an application until someone deals with it. An **incident** is one problem — a source, a code, what tells two of them apart —: opened when first reported, its repeats counted on it, resolved by hand or by itself when its cause goes away, opened again when it comes back. People acknowledge it, resolve it, or ignore it, with a note; who decided is kept.

What reports incidents:

- the **remotes**: every `RemoteSignal` of php-remote — a call failing, a check finding a service down, an answer with something wrong in it — becomes an incident, and its recovery closes it. Nothing to write: installing the bundle is enough;
- **heartbeats**: what must keep running — a nightly import — says it ran; a heartbeat without a success for longer than its interval opens an incident, a run that failed opens another;
- the application, through `IncidentService`.

An incident that opens or comes back alerts the people in charge through symfony-notification — once per opening, not per repeat. Each change goes to the subject's history when symfony-activity is installed. The screens are symfony-monitoring-ds's.

Not to be confused with symfony-activity, which keeps what happened to be read later, nor with symfony-check, which computes findings on objects without keeping them: an incident is a state, to be dealt with.
