# User event routing migration

The durable `user.notifications.q` queue accepts both generations on the user
exchange. Startup declares all four bindings idempotently before subscribing:

| Legacy routing key | Current routing key | Payload |
| --- | --- | --- |
| `user.registered` | `user.email_verification_requested` | `email`, `code` |
| `user.email_verified` | `user.welcome_email_requested` | `email`, `name` |

Legacy deliveries use the same handlers as current deliveries, including messages
already in the queue. Notification logs use the current event names. Producers
publish only once; do not dual-publish (that would send duplicate emails).

Deployment order:

1. Deploy this notification consumer and finish replacing **all** old consumer
   instances before upgrading auth producers. Old consumers cannot process the
   new routing keys. Startup adds the new bindings and retains the legacy ones;
   do not delete/recreate or purge the durable queue.
2. Upgrade auth producers to the current routing keys. Mixed producer versions
   work once all consumers support both generations. Roll producers back before
   rolling consumers back to versions without the current handlers.
3. Keep the aliases and legacy bindings through the rollback window and until
   no old producers remain. Drain legacy deliveries, including retries and any
   legacy messages in `failed.messages` selected for replay.
4. In a later coordinated migration, explicitly unbind `user.registered` and
   `user.email_verified` from `user.notifications.q` on the user exchange via
   RabbitMQ management or `QueueUnbind`, then remove the aliases and legacy
   entries from the topology. Removing entries from code alone does not remove
   durable RabbitMQ bindings. Never unbind while legacy producers are active.
