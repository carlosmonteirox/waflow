import { MigrationInterface, QueryRunner } from 'typeorm';

/** Stores encrypted Baileys credentials and Signal keys in the data database. */
export class AddBaileysAuthStates1782500000000 implements MigrationInterface {
  name = 'AddBaileysAuthStates1782500000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    if (await queryRunner.hasTable('baileys_auth_states')) return;
    const isPostgres = queryRunner.connection.options.type === 'postgres';
    const idDefault = isPostgres ? ' DEFAULT gen_random_uuid()::varchar' : '';
    const timestampType = isPostgres ? 'timestamp' : 'datetime';
    const now = isPostgres ? 'NOW()' : "(datetime('now'))";

    await queryRunner.query(
      `CREATE TABLE "baileys_auth_states" (` +
        `"id" varchar PRIMARY KEY NOT NULL${idDefault}, ` +
        `"sessionId" varchar NOT NULL, ` +
        `"category" varchar(80) NOT NULL, ` +
        `"keyId" varchar(512) NOT NULL, ` +
        `"encryptedPayload" text NOT NULL, ` +
        `"createdAt" ${timestampType} NOT NULL DEFAULT ${now}, ` +
        `"updatedAt" ${timestampType} NOT NULL DEFAULT ${now}, ` +
        `CONSTRAINT "FK_baileys_auth_states_sessionId" FOREIGN KEY ("sessionId") REFERENCES "sessions" ("id") ON DELETE CASCADE ON UPDATE NO ACTION)`,
    );
    await queryRunner.query(
      `CREATE UNIQUE INDEX "UQ_baileys_auth_states_session_category_key" ON "baileys_auth_states" ("sessionId", "category", "keyId")`,
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    await queryRunner.query(`DROP INDEX IF EXISTS "UQ_baileys_auth_states_session_category_key"`);
    await queryRunner.query(`DROP TABLE IF EXISTS "baileys_auth_states"`);
  }
}
