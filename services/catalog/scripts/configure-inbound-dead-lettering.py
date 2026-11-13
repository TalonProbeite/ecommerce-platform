#!/usr/bin/env python3
"""Apply on the broker host: python3 configure-inbound-dead-lettering.py VHOST QUEUE.

Requires rabbitmqctl on PATH. Existing queues are updated by policy, with no
recreation or message deletion. Competing policies must be merged by their owner.
"""
import json
import re
import subprocess
import sys


def ctl(*args):
    return subprocess.check_output(['rabbitmqctl', '-q', *args], text=True).strip()


def configure(vhost, queue):
    version = ctl('version')
    match = re.match(r'(\d+)\.(\d+)', version)
    if not match or tuple(map(int, match.groups())) < (3, 10):
        raise RuntimeError('RabbitMQ 3.10+ is required')
    flags = json.loads(ctl('list_feature_flags', 'name', 'state', '--formatter=json'))
    if not any(flag['name'] == 'stream_queue' and flag['state'] == 'enabled' for flag in flags):
        raise RuntimeError('Enable the stream_queue feature flag before applying this policy')
    name = 'catalog-at-least-once-' + queue
    definition = {'dead-letter-strategy': 'at-least-once', 'overflow': 'reject-publish'}
    pattern = '^' + re.escape(queue) + '$'
    for command in ('list_policies', 'list_operator_policies'):
        policies = json.loads(ctl(command, '-p', vhost, '--formatter=json'))
        for policy in policies:
            if isinstance(policy['definition'], str):
                policy['definition'] = json.loads(policy['definition'])
            if re.search(policy['pattern'], queue):
                if (command == 'list_policies' and policy['name'] == name
                        and policy['pattern'] == pattern and policy['definition'] == definition):
                    continue
                raise RuntimeError('Matching external policy found: ' + policy['name']
                                   + '. Ask its owner to merge dead-letter-strategy=at-least-once '
                                   + 'and overflow=reject-publish; verify effective queue policy before consuming.')
    ctl('set_policy', '-p', vhost, '--apply-to', 'quorum_queues', '--priority', '0',
        name, pattern, json.dumps(definition))
    print('Policy applied. Verify effective_policy_definition on the queue before consuming.')


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    configure(*sys.argv[1:])
