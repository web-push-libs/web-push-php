# Voluntary Application Server Identification

“VAPID” stands for “Voluntary Application Server Identification”.
This feature allows to application server to send information about itself to a push service.

A consistent identity can be used by a push service to establish behavioral expectations for an application server.
Significant deviations from an established norm can then be used to trigger exception-handling procedures.
Voluntarily provided contact information can be used to contact an application server operator in the case of exceptional situations.

Additionally, the design of [RFC8030] relies on maintaining the secrecy of push message subscription URIs.
Any application server in possession of a push message subscription URI is able to send messages to the user agent.
If use of a subscription could be limited to a single application server, this would reduce the impact
of the push message subscription URI being learned by an unauthorized party.

In order to use this feature, you must generate ECDSA key pairs. Hereafter an example using OpenSSL.

```sh
openssl ecparam -genkey -name prime256v1 -out private_key.pem
openssl ec -in private_key.pem -pubout -outform DER|tail -c 65|base64|tr -d '=' |tr '/+' '_-' >> public_key.txt
openssl ec -in private_key.pem -outform DER|tail -c +8|head -c 32|base64|tr -d '=' |tr '/+' '_-' >> private_key.txt
```
