import { ConfigService } from '@nestjs/config';
import { DataSource, Repository } from 'typeorm';
import { BaileysAuthState } from './baileys-auth-state.entity';
import { BaileysAuthStoreService } from './baileys-auth-store.service';

describe('BaileysAuthStoreService', () => {
  type FindOneArgs = { where: { sessionId: string; category: string; keyId: string } };
  type FindArgs = { where: { sessionId: string; category: string; keyId: { _value: string[] } } };
  let rows: BaileysAuthState[];
  let repository: Repository<BaileysAuthState>;
  let service: BaileysAuthStoreService;

  beforeEach(() => {
    rows = [];
    const repo = {
      findOne: jest.fn(({ where }: FindOneArgs) =>
        Promise.resolve(
          rows.find(r => r.sessionId === where.sessionId && r.category === where.category && r.keyId === where.keyId) ??
            null,
        ),
      ),
      find: jest.fn(({ where }: FindArgs) =>
        Promise.resolve(
          rows.filter(
            r =>
              r.sessionId === where.sessionId && r.category === where.category && where.keyId._value.includes(r.keyId),
          ),
        ),
      ),
      upsert: jest.fn((value: Partial<BaileysAuthState>) => {
        const index = rows.findIndex(
          r => r.sessionId === value.sessionId && r.category === value.category && r.keyId === value.keyId,
        );
        const row = {
          id: `row-${rows.length + 1}`,
          createdAt: new Date(),
          updatedAt: new Date(),
          ...value,
        } as BaileysAuthState;
        if (index >= 0) rows[index] = row;
        else rows.push(row);
        return Promise.resolve({});
      }),
      delete: jest.fn((where: Partial<BaileysAuthState>) => {
        rows = rows.filter(
          r => !Object.entries(where).every(([key, value]) => r[key as keyof BaileysAuthState] === value),
        );
        return Promise.resolve({ affected: 1 });
      }),
    };
    repository = repo as unknown as Repository<BaileysAuthState>;
    const dataSource = {
      transaction: jest.fn((callback: (manager: { getRepository: () => Repository<BaileysAuthState> }) => unknown) =>
        callback({ getRepository: () => repository }),
      ),
    } as unknown as DataSource;
    const config = { get: jest.fn(() => 'test-auth-encryption-key-with-32-characters') } as unknown as ConfigService;
    service = new BaileysAuthStoreService(repository, dataSource, config);
  });

  it('encrypts credentials and restores Buffer values across loads', async () => {
    const first = await service.load('session-1');
    first.state.creds.registered = true;
    (first.state.creds as unknown as { noiseKey: Buffer }).noiseKey = Buffer.from('secret-key');
    await first.saveCreds();

    expect(rows).toHaveLength(1);
    expect(rows[0].encryptedPayload).toMatch(/^v1:/);
    expect(rows[0].encryptedPayload).not.toContain('secret-key');

    const restored = await service.load('session-1');
    expect(restored.state.creds.registered).toBe(true);
    expect((restored.state.creds as unknown as { noiseKey: Buffer }).noiseKey.equals(Buffer.from('secret-key'))).toBe(
      true,
    );
  });

  it('persists and deletes Signal keys without touching the filesystem', async () => {
    const auth = await service.load('session-1');
    await auth.state.keys.set({
      'pre-key': { alpha: { keyPair: { public: Buffer.from('public'), private: Buffer.from('private') }, keyId: 1 } },
    });
    const restored = await auth.state.keys.get('pre-key', ['alpha']);
    expect(restored.alpha).toBeDefined();

    await auth.state.keys.set({ 'pre-key': { alpha: null } });
    expect(await auth.state.keys.get('pre-key', ['alpha'])).toEqual({});
  });

  it('clears all rows for a session only', async () => {
    const first = await service.load('session-1');
    const second = await service.load('session-2');
    await first.saveCreds();
    await second.saveCreds();

    await service.clearSession('session-1');
    expect(rows.map(row => row.sessionId)).toEqual(['session-2']);
  });
});
