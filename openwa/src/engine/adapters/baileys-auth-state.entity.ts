import {
  Column,
  CreateDateColumn,
  Entity,
  Index,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from 'typeorm';
import { Session } from '../../modules/session/entities/session.entity';

/** Encrypted Baileys credentials and Signal keys, persisted per OpenWA session. */
@Entity('baileys_auth_states')
@Index(['sessionId', 'category', 'keyId'], { unique: true })
export class BaileysAuthState {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column()
  sessionId: string;

  @ManyToOne(() => Session, { onDelete: 'CASCADE' })
  @JoinColumn({ name: 'sessionId' })
  session?: Session;

  @Column({ type: 'varchar', length: 80 })
  category: string;

  @Column({ type: 'varchar', length: 512 })
  keyId: string;

  @Column({ type: 'text' })
  encryptedPayload: string;

  @CreateDateColumn()
  createdAt: Date;

  @UpdateDateColumn()
  updatedAt: Date;
}
