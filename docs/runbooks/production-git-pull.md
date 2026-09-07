# Обновление кода на production (git pull)

Runbook для сервера **progs.com**: как подтянуть изменения из GitLab и что делать при ошибках доступа.

## Окружение

| Параметр | Значение |
|----------|----------|
| Пользователь на сервере | `www-root` |
| Каталог приложения | `~/www/progs.com` (полный путь: `/var/www/www-root/data/www/progs.com`) |
| Репозиторий GitLab | `https://gitlab.com/applicantsbimpro.ru-group/radarium.git` |
| Remote `origin` | GitLab (HTTPS) |
| Remote `github` | зеркало `https://github.com/falgrim/radarium.git` (опционально) |

Проверка:

```bash
cd ~/www/progs.com
git remote -v
```

## Рекомендуемый способ: Deploy Token (HTTPS)

На production используется **HTTPS** + **Deploy Token** проекта `radarium`. Пароль от аккаунта GitLab для `git pull` **не подходит**.

### Создание токена

1. GitLab → проект `applicantsbimpro.ru-group/radarium` → **Settings → Repository → Deploy tokens**.
2. **Add token**: имя (например `progs-prod-pull`), право **read_repository**.
3. После **Create deploy token** сразу скопировать оба значения с экрана:
   - **Username** — вида `gitlab+deploy-token-1234567` (это **не** логин пользователя GitLab);
   - **Token** — секретная строка (показывается **один раз**, позже не восстановить).

Если token потерян — создать новый deploy token; старый при необходимости **Revoke**.

### Первый git pull

```bash
cd ~/www/progs.com
git pull origin
```

| Запрос в терминале | Что вводить |
|------------------|-------------|
| `Username for 'https://gitlab.com':` | Username из deploy token (`gitlab+deploy-token-...`) |
| `Password for '...':` | Сам token (не пароль от gitlab.com) |

### Сохранить credentials (чтобы не вводить каждый раз)

```bash
git config --global credential.helper store
git pull origin
# один раз ввести deploy token username + token
chmod 600 ~/.git-credentials
```

Данные сохраняются в `~/.git-credentials` у пользователя `www-root`.

> **Безопасность:** не коммитьте token в репозиторий. Не вставляйте token в URL remote без необходимости (`git remote -v` тогда покажет секрет).

## После обновления кода

На сервере часто запускают пост-обработку из корня репозитория:

```bash
sh gitupdate.sh
```

Скрипт `gitupdate.sh` выставляет права на `storage/`, каталоги MadelineProto-сессий, лог и выполняет `artisan optimize` + `schedule:interrupt`.

## Альтернатива: SSH (deploy key)

На сервере может быть настроен SSH-ключ вне `~/.ssh/`:

- приватный ключ: `/var/www/www-root/data/gitlab_radarium`;
- `~/.ssh/config` для `Host gitlab.com` с `IdentityFile` и `IdentitiesOnly yes`.

Для `git pull` по **HTTPS** deploy key **не используется**. SSH нужен только если remote переключён на:

```bash
git remote set-url origin git@gitlab.com:applicantsbimpro.ru-group/radarium.git
```

### Типовые проблемы SSH

| Симптом | Причина | Решение |
|---------|---------|---------|
| `Permission denied (publickey)` | Ключ на сервере есть, но не принят GitLab для этого проекта | В GitLab: **Deploy keys** → включить ключ в **Enabled** или **Available** |
| `Fingerprint sha256 has already been taken` при Add key | Публичный ключ уже зарегистрирован (другой проект, группа или личный SSH Keys) | Найти и **Enable** для `radarium`, либо сгенерировать **новую** пару ключей |
| Ключа нет в списке Deploy keys проекта | Зарегистрирован как личный SSH-ключ пользователя или на другом проекте | Проверить **Profile → SSH Keys** и deploy keys других проектов группы |

Создание новой пары (если старый ключ «застрял» в GitLab):

```bash
ssh-keygen -t ed25519 -C "progs-prod-YYYY" -f /var/www/www-root/data/gitlab_radarium_new -N ""
cat /var/www/www-root/data/gitlab_radarium_new.pub
# добавить .pub в Deploy keys проекта radarium, обновить ~/.ssh/config
```

Команда `ssh-keygen -y -f <приватный_ключ>` **не создаёт** новый ключ — только показывает публичную часть существующего.

## Personal Access Token (не рекомендуется для прода)

Если группа требует fine-grained token, classic PAT с `read_repository` даёт **403**:

```text
This operation requires a fine-grained personal access token with the following project permissions: [Code: Download].
```

Для PAT: **Fine-grained** token с правом **Code: Download** на проект `radarium`; username — логин GitLab (`applicantsbimpro.ru`) или `oauth2`, password — `glpat-...`.

Для сервера проще и безопаснее **Deploy Token**.

## Диагностика

```bash
# remote и подмена URL
git remote -v
git config --list --show-origin | grep -i insteadof

# HTTPS: тест pull
git pull origin

# SSH (если используется): тест ключа
ssh -T git@gitlab.com -v
```

## История инцидента (2026-07-08)

На production `git pull` перестал работать: сначала ошибка SSH (`Permission denied (publickey)`), затем при переходе на HTTPS — запрос логина и **403** из-за classic PAT. Решение: **Deploy Token** с `read_repository` + `credential.helper store`.

---

**См. также:** `docs/RADARIUM_TECHDOC.md` §15, `gitupdate.sh`, `docs/runbooks/madelineproto-vpn-routing.md` (сеть/VPN на проде).
