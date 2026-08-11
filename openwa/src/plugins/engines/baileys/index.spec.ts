jest.mock('../../../engine/adapters/baileys.adapter', () => ({
  BaileysAdapter: jest.fn().mockImplementation((config: unknown) => ({ config })),
}));

import { BaileysPlugin } from './index';
import { BaileysAdapter } from '../../../engine/adapters/baileys.adapter';
import type { BaileysAuthStore, BaileysMessageStore } from '../../../engine/types/baileys.types';

describe('BaileysPlugin.createEngine', () => {
  const authStore = (): BaileysAuthStore => ({
    load: jest.fn(),
    clearSession: jest.fn(),
  });

  beforeEach(() => jest.clearAllMocks());

  it('passes database auth and engine-neutral fields to the adapter', () => {
    const auth = authStore();
    const plugin = new BaileysPlugin(undefined, auth);
    plugin.createEngine({ sessionId: 'sess-1', dbSessionId: 'db-1', proxyUrl: 'http://p', proxyType: 'http' });

    expect(BaileysAdapter).toHaveBeenCalledWith(
      expect.objectContaining({
        sessionId: 'sess-1',
        dbSessionId: 'db-1',
        authStore: auth,
        proxyUrl: 'http://p',
        proxyType: 'http',
      }),
    );
  });

  it('fails closed when the database auth store is unavailable', () => {
    expect(() => new BaileysPlugin().createEngine({ sessionId: 'sess-2', dbSessionId: 'db-2' })).toThrow(
      /database auth store/i,
    );
  });

  it('passes message and lid stores to the adapter', () => {
    const messageStore = {
      put: jest.fn(),
      getMessage: jest.fn(),
      clearSession: jest.fn(),
    } as BaileysMessageStore;
    const lidStore = { remember: jest.fn(), getCached: jest.fn(), lidsForPhone: jest.fn() };
    new BaileysPlugin(messageStore, authStore(), lidStore).createEngine({ sessionId: 'sess-1', dbSessionId: 'db-1' });

    expect(BaileysAdapter).toHaveBeenCalledWith(
      expect.objectContaining({ sessionId: 'sess-1', messageStore, lidMappingStore: lidStore }),
    );
  });

  it('advertises the Baileys supported feature set', () => {
    expect(new BaileysPlugin().getFeatures()).toEqual([
      'text-messages',
      'typing-indicator',
      'media-messages',
      'location-messages',
      'contact-messages',
      'message-replies',
      'message-forwarding',
      'message-reactions',
      'message-deletion',
      'group-management',
      'read-receipts',
    ]);
  });

  it('reports the Baileys library name', () => {
    expect(new BaileysPlugin().getEngineLibrary().name).toBe('@whiskeysockets/baileys');
  });
});
