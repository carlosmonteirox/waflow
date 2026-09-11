# Segurança do Waflow

Este documento descreve as fronteiras de confiança, os controles implementados
e os riscos aceitos. Valores operacionais podem ser alterados pelas variáveis em
`infra/.env.example`.

## Fronteiras de confiança

```text
Internet -> Nginx -> Laravel -> PostgreSQL/Redis
                         |
                         +-> worker -> OpenWA/Baileys -> WhatsApp
                         +-> webhooks HTTPS de clientes
                         +-> Banco Inter por OAuth2 + mTLS
```

- Somente o Nginx publica uma porta. OpenWA, PostgreSQL e Redis permanecem em
  redes privadas do Compose.
- Laravel autentica e autoriza o contrato público. OpenWA recebe uma credencial
  mestra apenas na rede privada.
- Workers e OpenWA possuem egress porque entregam mensagens e callbacks.
- O IP remoto só pode ser substituído por cabeçalhos encaminhados quando
  `WAFLOW_TRUSTED_PROXIES` contém IPs ou CIDRs explícitos. `*` não é aceito como
  configuração operacional.

## Autenticação e autorização

- Chaves de API são armazenadas como SHA-256 e exibidas em texto puro uma única
  vez.
- Cada rota exige uma ability específica. Uma chave restrita só pode delegar um
  subconjunto das próprias abilities.
- Contas do painel usam senha de ao menos 12 caracteres, maiúsculas, minúsculas,
  números e símbolos, seguida por código de e-mail de uso único por cinco
  minutos.
- Recuperação de senha devolve a mesma resposta para contas existentes e
  inexistentes.
- Sessões são criptografadas; cookies são `Secure` no perfil de produção.
- Papéis de equipe e escopo por workspace são verificados no servidor. O cliente
  não é uma fronteira de autorização.

## Limites padrão

Limites específicos são cumulativos com o limite geral. Quando mais de um se
aplica, o primeiro teto atingido devolve HTTP 429.

| Superfície | Chave do contador | Limite |
|---|---|---:|
| Cadastro web/API | IP | 5/hora |
| Login do painel | IP + e-mail do formulário | 5/minuto |
| Solicitação de recuperação | IP | 3/minuto |
| Redefinição de senha | IP | 5/minuto |
| Desafio 2FA | sessão/IP | 10/minuto |
| Reenvio 2FA | sessão/IP | 2/minuto |
| API antes da autenticação | IP | 300/minuto |
| API antes da autenticação | hash da credencial apresentada | 120/minuto |
| API autenticada | ID da chave | 120/minuto |
| Envio unitário de mensagem | ID da chave | 60/minuto |
| Envio em lote | ID da chave | 10/minuto |
| Teste de webhook/criação de chave | ID da chave | 20/minuto |
| Envio pelo painel | usuário/IP | 30/minuto |
| Criação de webhook pelo painel | usuário/IP | 10/minuto |
| Callback interno do OpenWA | IP | 300/minuto |
| Callback do Banco Inter | IP | 60/minuto |

O rate limiting reduz abuso e custo, mas não substitui limites no balanceador,
WAF/CDN, controle de concorrência dos workers e quotas comerciais.

## Entradas, saídas e webhooks

- O gateway limita requisições a 16 MiB. Mídia Base64 é validada estritamente e
  limitada a 10 MiB depois da decodificação. Callbacks internos aceitam no
  máximo 1 MiB e até 100 eventos do Inter por entrega.
- URLs de webhook de clientes não aceitam credenciais embutidas, portas não
  padrão, endereços privados/reservados ou host cujo A/AAAA resolva para rede
  privada. O destino é revalidado na entrega, o DNS é fixado para a conexão e
  redirects permanecem desativados.
- Callbacks de clientes são assinados com HMAC-SHA256 sobre o corpo bruto. O
  segredo de assinatura fica criptografado no banco.
- O callback OpenWA também exige HMAC e possui deduplicação.
- O callback do Inter usa um segredo de alta entropia no caminho, valida o
  schema e não é escrito no access log do Nginx. Rotacione esse segredo após
  qualquer suspeita e recadastre os dois webhooks.
- Conteúdo exibido em tabelas Vue é escapado. O Nginx envia CSP, HSTS,
  `nosniff`, política de referrer, frame e permissions policy.

## Dados e containers

- Estado de autenticação e Signal keys do Baileys usam AES-256-GCM. A chave de
  criptografia precisa permanecer fora do repositório e entrar no backup de
  segredos.
- Certificado e chave do Inter são montados somente para leitura a partir de
  `infra/secrets/`, que é ignorado pelo Git.
- OpenWA roda sem root, com filesystem somente leitura, `no-new-privileges`,
  capabilities reduzidas e limites de memória/PIDs.
- A imagem Waflow é exclusiva para Baileys e não contém Chromium,
  `whatsapp-web.js`, Puppeteer ou dependências de desenvolvimento.

## Supply chain e CI

- Lockfiles são obrigatórios. CI executa testes, lint, build, auditorias do npm e
  Composer, teste em PostgreSQL 16 e build das imagens.
- CodeQL analisa JavaScript/TypeScript em pushes, pull requests e semanalmente.
- Dependabot verifica Composer, npm, Docker e GitHub Actions semanalmente.
- GitHub Actions de terceiros são fixadas por commit SHA.
- A auditoria da imagem Baileys aceita temporariamente um alerta moderado do
  `adm-zip`. O caminho usado pelo instalador lê entradas em memória, limita o
  tamanho e rejeita traversal; ele não extrai arquivos seguindo symlinks. A
  exceção deve ser reavaliada quando houver uma versão corrigida.
- Vulnerabilidades altas da árvore opcional `whatsapp-web.js` não entram na
  imagem Waflow. O CI ainda falha se essa árvore alcançar severidade crítica.

## Riscos residuais

- Baileys usa um protocolo não oficial do WhatsApp. Logout, mudança de protocolo
  e restrição de número são riscos operacionais que código local não elimina.
- O segredo no caminho do callback Inter é um controle compensatório do Waflow;
  mantenha HTTPS, segredo longo, logs suprimidos, conciliação periódica via API e
  monitore mudanças no contrato do provedor.
- CSP ainda permite `style-src 'unsafe-inline'` por compatibilidade com a
  interface atual. Removê-lo exige migrar estilos inline para classes/nonces.
- Sinais antiabuso de trial reduzem reincidência, mas não comprovam identidade.
  Adicione CAPTCHA/risk scoring e reputação conforme o volume crescer.

## Reporte e resposta

Não publique credenciais ou detalhes exploráveis em issues públicas. Use um
canal privado do mantenedor ou um GitHub Security Advisory privado. Na resposta
a incidente: revogue chaves afetadas, rotacione segredos, preserve logs e IDs de
requisição, contenha egress quando necessário, restaure de backup verificado e
registre causa, impacto e prevenção.
