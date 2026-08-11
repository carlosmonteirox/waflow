import { Global, Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { EngineFactory } from './engine.factory';
import { BaileysStoredMessage } from './adapters/baileys-stored-message.entity';
import { BaileysMessageStoreService } from './adapters/baileys-message-store.service';
import { LidMapping } from './identity/lid-mapping.entity';
import { LidMappingStoreService } from './identity/lid-mapping-store.service';
import { BaileysAuthState } from './adapters/baileys-auth-state.entity';
import { BaileysAuthStoreService } from './adapters/baileys-auth-store.service';

@Global()
@Module({
  imports: [TypeOrmModule.forFeature([BaileysStoredMessage, BaileysAuthState, LidMapping], 'data')],
  providers: [EngineFactory, BaileysMessageStoreService, BaileysAuthStoreService, LidMappingStoreService],
  exports: [EngineFactory, LidMappingStoreService],
})
export class EngineModule {}
