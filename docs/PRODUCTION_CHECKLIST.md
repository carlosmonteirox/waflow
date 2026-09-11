# Checklist de produção

Execute `make preflight` antes de cada primeira implantação e após mudanças de
domínio, proxy, certificado ou provedor. Não considere o ambiente liberado se um
item obrigatório estiver pendente.

## 1. Domínio, TLS e proxy

- [ ] `APP_URL` e `INTER_WEBHOOK_URL` usam HTTPS e o certificado público cobre o
  hostname completo.
- [ ] HTTP redireciona para HTTPS no balanceador/ingress e HSTS foi confirmado
  somente depois que HTTPS estiver estável.
- [ ] `SESSION_SECURE_COOKIE=true` e `SESSION_ENCRYPT=true`.
- [ ] Se houver load balancer, `WAFLOW_TRUSTED_PROXIES` contém somente seus IPs
  ou CIDRs. Nunca use `*`, `**` ou uma rede mais ampla que a infraestrutura.
- [ ] O proxy externo sobrescreve, em vez de concatenar cegamente, os headers
  `X-Forwarded-*` recebidos da internet.
- [ ] OpenWA, PostgreSQL e Redis não publicam portas; apenas o gateway está
  acessível externamente.
- [ ] CSP, HSTS, `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy` e `Permissions-Policy` aparecem na resposta pública.

## 2. Segredos e chaves

- [ ] `APP_KEY`, senha PostgreSQL, chave mestra OpenWA, chave AES do Baileys e
  segredos dos webhooks foram gerados aleatoriamente e não estão no Git/CI log.
- [ ] Segredos de produção diferem de desenvolvimento, staging e valores
  anteriores comprometidos.
- [ ] Chaves estão em secret manager ou arquivo `infra/.env` com permissão 600;
  acesso humano segue menor privilégio.
- [ ] Há procedimento testado de rotação para API keys, `OPENWA_WEBHOOK_SECRET`
  e `INTER_WEBHOOK_SECRET`.
- [ ] `BAILEYS_AUTH_ENCRYPTION_KEY` e `APP_KEY` estão no backup de segredos. Não
  as rotacione sem migração, pois os dados existentes dependem delas.

## 3. Banco Inter e cobrança

- [ ] Integração de produção está habilitada com escopos mínimos necessários e
  OAuth2/mTLS; certificado e chave ficam em `infra/secrets/` somente leitura.
- [ ] Certificado não está vencido e há alerta pelo menos 30 dias antes da
  expiração; a chave privada corresponde ao certificado.
- [ ] Os webhooks `rec` e `cobr` foram cadastrados com
  `php artisan billing:inter:configure-webhooks`.
- [ ] O caminho contendo o segredo Inter não aparece em access logs, APM,
  analytics, traces, alertas ou histórico de tickets.
- [ ] Payload inválido/maior que 1 MiB devolve erro e HTTP 429 aparece após o
  limite configurado, sem derrubar callbacks válidos.
- [ ] Uma rotina concilia periodicamente recorrências, cobranças, pagamentos e
  assinaturas pela API, inclusive após indisponibilidade de webhook. A
  documentação do Inter prevê novas tentativas do callback, mas reconciliação
  continua sendo obrigatória para operação financeira segura.
- [ ] Alertas cobrem falhas OAuth/mTLS, callbacks rejeitados, divergência de
  valores/status, duplicidade e atraso de cobrança.

Referência do provedor: [API Pix Automático do Inter](https://developers.inter.co/references/pix-automatico).

## 4. Mensageria e webhooks de clientes

- [ ] Um número dedicado foi conectado e o procedimento de reconexão/novo QR foi
  testado sem perder estado do cliente.
- [ ] Ramp-up e limites por cliente/número foram definidos abaixo da capacidade
  real dos workers e do provedor.
- [ ] Idempotency keys são obrigatórias nos clientes para operações repetíveis.
- [ ] Retentativas possuem backoff e fila de falhas; alertas distinguem erro
  transitório, credencial inválida e bloqueio do número.
- [ ] Clientes validam `X-Waflow-Signature` sobre o corpo bruto e deduplicam
  `X-Waflow-Delivery`.
- [ ] Webhooks de produção usam HTTPS; destinos antigos/inativos foram removidos.
- [ ] O risco de Baileys não ser a API oficial da Meta foi aceito formalmente.

## 5. Dados, backup e privacidade

- [ ] Backup automatizado inclui PostgreSQL, `laravel-storage`, `openwa-data` e
  segredos necessários para descriptografia.
- [ ] Restore foi exercitado em ambiente isolado e RPO/RTO foram registrados.
- [ ] Backup é criptografado, versionado, retido fora do host e tem acesso
  auditável.
- [ ] Retenção e exclusão cobrem mensagens, mídia, contatos, payloads de
  webhook, logs, auditoria e sinais antiabuso conforme a política/LGPD.
- [ ] Logs não contêm tokens, API keys, documentos completos, mídia Base64,
  segredos de webhook ou códigos 2FA de produção.

## 6. Capacidade e observabilidade

- [ ] Há métricas/alertas de HTTP 5xx/429/latência, filas e retries, jobs falhos,
  conexões OpenWA, espaço de volumes, PostgreSQL, Redis, CPU, memória e PIDs.
- [ ] O número de workers e `--timeout` foram ajustados por teste de carga; a
  fila não cresce indefinidamente no pico esperado.
- [ ] Limites WAF/CDN e Laravel foram testados com IP real através do proxy.
- [ ] Health checks público e internos são monitorados de fora do host.
- [ ] Relógio/NTP, timezone e janela do scheduler foram verificados.

## 7. Release e recuperação

- [ ] `make quality`, `make test-postgres`, auditorias e CI estão verdes.
- [ ] Imagens foram construídas a partir do commit liberado e identificadas por
  tag imutável; não publique `local` ou `latest` como release auditável.
- [ ] Migrations foram revisadas quanto a lock, duração, reversibilidade e
  backup; deploy faz migration uma única vez antes dos workers.
- [ ] Plano de rollback considera incompatibilidade de schema e restauração de
  segredo/volume, não apenas imagem anterior.
- [ ] O teste smoke confirmou gateway, Laravel, PostgreSQL, Redis e OpenWA.
- [ ] `make e2e-live` passou com uma instância e destinatário controlados; o
  envio real foi registrado e conciliado uma única vez.
- [ ] Contatos de incidente, janela de manutenção e responsáveis por rollback,
  banco, WhatsApp e cobrança estão definidos.

## Comandos de evidência

```bash
make preflight
make quality
make test-postgres
docker compose --env-file infra/.env -f infra/compose.yml build gateway laravel openwa
docker compose --env-file infra/.env -f infra/compose.yml up -d
make health
make e2e-live
```

Arquive o commit, outputs da CI, resultado do restore, versão das imagens e
aprovação do checklist junto à release.
