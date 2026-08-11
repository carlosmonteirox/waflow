import * as crypto from 'crypto';
import { Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { InjectDataSource, InjectRepository } from '@nestjs/typeorm';
import type * as BaileysLib from '@whiskeysockets/baileys';
import type { AuthenticationCreds, AuthenticationState, SignalDataTypeMap } from '@whiskeysockets/baileys';
import { DataSource, In, Repository } from 'typeorm';
import { BaileysAuthState } from './baileys-auth-state.entity';
import { BaileysAuthStore } from '../types/baileys.types';

const CREDENTIALS_CATEGORY = 'credentials';
const CREDENTIALS_KEY = 'primary';
const CIPHER_VERSION = 'v1';

@Injectable()
export class BaileysAuthStoreService implements BaileysAuthStore {
  private baileysLib?: typeof BaileysLib;
  private encryptionKey?: Buffer;

  constructor(
    @InjectRepository(BaileysAuthState, 'data')
    private readonly repo: Repository<BaileysAuthState>,
    @InjectDataSource('data')
    private readonly dataSource: DataSource,
    private readonly configService: ConfigService,
  ) {}

  async load(sessionId: string): Promise<{ state: AuthenticationState; saveCreds: () => Promise<void> }> {
    const baileys = await this.loadLib();
    const credentials =
      (await this.read<AuthenticationCreds>(sessionId, CREDENTIALS_CATEGORY, CREDENTIALS_KEY)) ??
      baileys.initAuthCreds();

    const state: AuthenticationState = {
      creds: credentials,
      keys: {
        get: async <T extends keyof SignalDataTypeMap>(type: T, ids: string[]) => {
          const rows = ids.length ? await this.repo.find({ where: { sessionId, category: type, keyId: In(ids) } }) : [];
          const byKey = new Map(rows.map(row => [row.keyId, row]));
          const result: { [id: string]: SignalDataTypeMap[T] } = {};

          for (const id of ids) {
            const row = byKey.get(id);
            if (!row) continue;
            let value = await this.decrypt<SignalDataTypeMap[T]>(row.encryptedPayload, this.aad(sessionId, type, id));
            if (type === 'app-state-sync-key' && value) {
              value = baileys.proto.Message.AppStateSyncKeyData.fromObject(
                value as unknown as Record<string, unknown>,
              ) as unknown as SignalDataTypeMap[T];
            }
            result[id] = value;
          }

          return result;
        },
        set: async data => {
          await this.dataSource.transaction(async manager => {
            const repository = manager.getRepository(BaileysAuthState);
            for (const [category, entries] of Object.entries(data)) {
              for (const [keyId, value] of Object.entries(entries ?? {})) {
                if (value == null) {
                  await repository.delete({ sessionId, category, keyId });
                  continue;
                }
                await repository.upsert(
                  {
                    sessionId,
                    category,
                    keyId,
                    encryptedPayload: await this.encrypt(value, this.aad(sessionId, category, keyId)),
                  },
                  ['sessionId', 'category', 'keyId'],
                );
              }
            }
          });
        },
        clear: async () => this.clearSession(sessionId),
      },
    };

    return {
      state,
      saveCreds: () => this.write(sessionId, CREDENTIALS_CATEGORY, CREDENTIALS_KEY, state.creds),
    };
  }

  async clearSession(sessionId: string): Promise<void> {
    await this.repo.delete({ sessionId });
  }

  private async read<T>(sessionId: string, category: string, keyId: string): Promise<T | null> {
    const row = await this.repo.findOne({ where: { sessionId, category, keyId } });
    return row ? this.decrypt<T>(row.encryptedPayload, this.aad(sessionId, category, keyId)) : null;
  }

  private async write(sessionId: string, category: string, keyId: string, value: unknown): Promise<void> {
    await this.repo.upsert(
      {
        sessionId,
        category,
        keyId,
        encryptedPayload: await this.encrypt(value, this.aad(sessionId, category, keyId)),
      },
      ['sessionId', 'category', 'keyId'],
    );
  }

  private async encrypt(value: unknown, aad: string): Promise<string> {
    const { BufferJSON } = await this.loadLib();
    const iv = crypto.randomBytes(12);
    const cipher = crypto.createCipheriv('aes-256-gcm', this.key(), iv);
    cipher.setAAD(Buffer.from(aad));
    const plaintext = JSON.stringify(value, BufferJSON.replacer);
    const encrypted = Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()]);
    return [
      CIPHER_VERSION,
      iv.toString('base64'),
      cipher.getAuthTag().toString('base64'),
      encrypted.toString('base64'),
    ].join(':');
  }

  private async decrypt<T>(payload: string, aad: string): Promise<T> {
    const [version, iv, tag, encrypted] = payload.split(':');
    if (version !== CIPHER_VERSION || !iv || !tag || !encrypted) {
      throw new Error('Unsupported or corrupted Baileys auth payload');
    }
    const decipher = crypto.createDecipheriv('aes-256-gcm', this.key(), Buffer.from(iv, 'base64'));
    decipher.setAAD(Buffer.from(aad));
    decipher.setAuthTag(Buffer.from(tag, 'base64'));
    const plaintext = Buffer.concat([decipher.update(Buffer.from(encrypted, 'base64')), decipher.final()]).toString(
      'utf8',
    );
    const { BufferJSON } = await this.loadLib();
    return JSON.parse(plaintext, BufferJSON.reviver) as T;
  }

  private key(): Buffer {
    if (this.encryptionKey) return this.encryptionKey;
    const configured = this.configService.get<string>('engine.baileys.authEncryptionKey')?.trim();
    if (!configured || configured.length < 32) {
      throw new Error('BAILEYS_AUTH_ENCRYPTION_KEY must contain at least 32 characters');
    }
    this.encryptionKey = crypto.createHash('sha256').update(configured).digest();
    return this.encryptionKey;
  }

  private aad(sessionId: string, category: string, keyId: string): string {
    return `${sessionId}:${category}:${keyId}`;
  }

  private async loadLib(): Promise<typeof BaileysLib> {
    return (this.baileysLib ??= await import('@whiskeysockets/baileys'));
  }
}
