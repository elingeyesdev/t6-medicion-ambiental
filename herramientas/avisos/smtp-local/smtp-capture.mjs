import { createRequire } from 'node:module';
import { writeFileSync, renameSync } from 'node:fs';
import { format } from 'node:util';

// The SMTP implementation is the pinned public package, never a protocol fake.
export async function startCapture(dependencyDir, snapshotFile) {
  const require = createRequire(dependencyDir + '/package.json');
  const { SMTPServer } = require('smtp-server');
  const state = { host: '127.0.0.1', port: null, closed: false, events: [], messages: [] };
  function persist() {
    writeFileSync(snapshotFile + '.next', JSON.stringify(state, null, 2) + '\n');
    renameSync(snapshotFile + '.next', snapshotFile);
  }
  function record(event) { state.events.push(event); persist(); }
  const logger = {};
  for (const level of ['trace', 'debug', 'info', 'warn', 'error', 'fatal']) {
    logger[level] = (meta, message, ...args) => {
      if (meta && ['command', 'send'].includes(meta.tnx)) {
        record({ type: 'protocol', session: meta.cid, direction: meta.tnx, text: format(message, ...args) });
      }
    };
  }
  let rejectNextConnection = false;
  const synthetic = address => /^[a-z0-9._+-]+@example\.test$/i.test(address);
  const server = new SMTPServer({
    name: 'smtp65.example.test', banner: 'GeoSur local protocol capture only',
    secure: false, disabledCommands: ['AUTH', 'STARTTLS'], disableReverseLookup: true,
    logger, size: 65536, maxClients: 4, socketTimeout: 5000, closeTimeout: 1000,
    onConnect(session, callback) {
      const restartRejected = rejectNextConnection;
      rejectNextConnection = false;
      record({ type: 'connect', session: session.id, remote: session.remoteAddress, reply: restartRejected ? 554 : 220 });
      if (restartRejected) return callback(Object.assign(new Error('SMTP132_REINICIO_RECHAZADO'), { responseCode: 554 }));
      callback(session.remoteAddress === '127.0.0.1' ? undefined : new Error('Loopback only'));
    },
    onMailFrom(address, session, callback) {
      record({ type: 'mail', session: session.id, from: address.address });
      if (!synthetic(address.address)) return callback(Object.assign(new Error('Synthetic sender only'), { responseCode: 550 }));
      callback();
    },
    onRcptTo(address, session, callback) {
      const reply = address.address === 'rechazo@example.test' || !synthetic(address.address) ? 550 :
        address.address === 'temporal@example.test' ? 450 : 250;
      record({ type: 'rcpt', session: session.id, to: address.address, reply });
      callback(reply !== 250 ? Object.assign(new Error('SMTP65_RECHAZO_SINTETICO'), { responseCode: reply }) : undefined);
    },
    onData(stream, session, callback) {
      const chunks = [];
      stream.on('data', chunk => chunks.push(chunk));
      stream.on('error', callback);
      stream.on('end', () => {
        const to = session.envelope.rcptTo.map(rcpt => rcpt.address);
        const uncertain = to.includes('incierto@example.test');
        state.messages.push({ session: session.id, from: session.envelope.mailFrom.address, to,
          data: Buffer.concat(chunks).toString('utf8'), finalReply: uncertain ? null : 250 });
        record({ type: 'data', session: session.id, to, finalReply: uncertain ? null : 250 });
        // Deliberately withhold the final reply. The real PHP socket must time out.
        if (to.includes('reinicio@example.test')) rejectNextConnection = true;
        if (!uncertain) callback(null, 'SMTP65_LOCAL_ACCEPTED');
      });
    },
    onClose(session) { record({ type: 'close', session: session.id }); },
  });
  server.on('error', error => record({ type: 'server-error', code: error.code || 'SMTP_ERROR' }));
  await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', () => { server.removeListener('error', reject); resolve(); });
  });
  state.port = server.server.address().port;
  persist();
  return {
    state,
    async close() {
      await new Promise(resolve => server.close(resolve));
      state.closed = true;
      state.openSessionsAtClose = state.events.filter(e => e.type === 'connect').length - state.events.filter(e => e.type === 'close').length;
      persist();
    },
  };
}
