# macOS Development Environment

My daily macOS setup for full-stack development, terminal workflows, containers, APIs, databases, and CLI automation.

## Primary Development Stack

- **Terminal:** Ghostty + tmux
- **Shell:** Zsh + Oh My Zsh + Starship + Atuin
- **Navigation/Search:** fzf, fd, ripgrep, ripgrep-all, zoxide, Yazi
- **Editor:** Neovim (lazy.nvim) + BBEdit
- **Git:** lazygit + delta
- **Containers:** Colima + Docker
- **Runtime/Versioning:** mise + pnpm
- **API/DB:** Bruno, Stripe CLI, mysql-client, Sequel Ace, Medis

## Terminal And Shell

### Terminal

- **[Ghostty](https://github.com/ghostty-org/ghostty)** - fast GPU-accelerated terminal
- **[tmux](https://github.com/tmux/tmux)** - persistent sessions, split panes, and keyboard-first terminal workflows

### Shell

- **[Oh My Zsh](https://github.com/ohmyzsh/ohmyzsh)** - Zsh configuration framework
- **[Starship](https://starship.rs)** - active prompt setup
- **[zsh-autosuggestions](https://github.com/zsh-users/zsh-autosuggestions)** - inline command suggestions
- **[zsh-syntax-highlighting](https://github.com/zsh-users/zsh-syntax-highlighting)** - command syntax highlighting
- **[Atuin](https://github.com/atuinsh/atuin)** - searchable shell history with sync

> Previously used: [Powerlevel10k](https://github.com/romkatv/powerlevel10k)

## Navigation And CLI Workflow

- **[Yazi](https://github.com/sxyazi/yazi)** - terminal file manager
- **[fzf](https://github.com/junegunn/fzf)** - fuzzy finder for terminal workflows
- **[ripgrep](https://github.com/BurntSushi/ripgrep)** - fast regex file search
- **[ripgrep-all](https://github.com/phiresky/ripgrep-all)** - search across PDFs, docs, and archives
- **[fd](https://github.com/sharkdp/fd)** - modern alternative to `find`
- **[zoxide](https://github.com/ajeetdsouza/zoxide)** - smarter directory navigation
- **[bat](https://github.com/sharkdp/bat)** - `cat` with syntax highlighting and Git integration
- **[eza](https://github.com/eza-community/eza)** - modern replacement for `ls`
- **[tldr](https://github.com/tldr-pages/tldr)** - simplified command cheat sheets
- **[just](https://github.com/casey/just)** - command runner for project-specific tasks
- **[lnav](https://lnav.org)** - terminal log viewer with search and filtering
- **[btop](https://github.com/aristocratos/btop)** - terminal resource monitor
- **[GitHub Copilot CLI](https://github.com/github/copilot-cli)** - terminal-native Copilot workflow entrypoint

### AI Workflow Critical Additions

- **[repomix](https://github.com/yamadashy/repomix)** - package repository context for LLM prompts
- **[files-to-prompt](https://github.com/simonw/files-to-prompt)** - concatenate targeted file sets with path headers
- **[code2prompt](https://github.com/mufeedvh/code2prompt)** - template-driven prompt/context generation
- **[watchexec](https://github.com/watchexec/watchexec)** - file-watch command loop for edit -> verify cycles
- **[direnv](https://direnv.net/)** - auto-load per-directory environment variables
- **[semgrep](https://semgrep.dev/)** - static analysis and security scanning
- **[difftastic](https://github.com/Wilfred/difftastic)** - syntax-aware structural diffing for review
- **[shellcheck](https://github.com/koalaman/shellcheck)** - shell linting for repo scripts and hooks
- **[shfmt](https://github.com/mvdan/sh)** - shell formatting check for tracked scripts
- **[actionlint](https://github.com/rhysd/actionlint)** - GitHub Actions workflow validation
- **[lychee](https://github.com/lycheeverse/lychee)** - Markdown and text link checking for repo docs
- **[bats-core](https://github.com/bats-core/bats-core)** - shell test runner used by `tests/shell/`
- **[yq](https://github.com/mikefarah/yq)** - YAML querying for Copilot command policy loading

## Editors And Git

- **[Neovim](https://github.com/neovim/neovim)** - extensible Vim-based editor
  Plugin manager: **[lazy.nvim](https://lazy.folke.io)** - configured in `configs/nvim/`
- **[BBEdit](https://www.barebones.com/products/bbedit/)** - lightweight macOS text editor
- **[lazygit](https://github.com/jesseduffield/lazygit)** - terminal UI for Git workflows
- **[delta](https://github.com/dandavison/delta)** - syntax-highlighted Git diff pager

## Containers, Runtime, And Package Management

- **[colima](https://github.com/abiosoft/colima)** - lightweight container runtime for macOS
- **[docker](https://docs.docker.com/engine/reference/commandline/cli/)** - Docker CLI with modern `docker compose` workflow
- **[mise](https://github.com/jdx/mise)** - polyglot tool version manager
- **[pnpm](https://github.com/pnpm/pnpm)** - fast Node.js package manager

## API And Database Tooling

- **[Bruno](https://www.usebruno.com)** - open-source API client with plain-text collections
- **[stripe](https://stripe.com/docs/stripe-cli)** - Stripe CLI for local webhook testing
- **[mysql-client](https://dev.mysql.com/doc/refman/en/programs-client.html)** - MySQL CLI client tools
- **[Sequel Ace](https://github.com/Sequel-Ace/Sequel-Ace)** - MySQL and MariaDB GUI
- **[Medis](https://github.com/luin/medis)** - Redis GUI client

## Workspace And Window Management

- **[AeroSpace](https://github.com/nikitabobko/AeroSpace)** - tiling window manager
- **[IntelliJ IDEA Community](https://www.jetbrains.com/idea/)** - useful for Java work and merge conflict resolution

## Install Via Homebrew

> # Requires [Homebrew](https://brew.sh/) - install it first if not present:

## Container & Docker

- **[colima](https://github.com/abiosoft/colima)** - lightweight container runtime for macOS (runs Docker daemon via Lima VM)
- **[docker](https://docs.docker.com/engine/reference/commandline/cli/)** - Docker CLI
- **[docker-compose](https://docs.docker.com/compose/)** - multi-container orchestration

## Browser

- **[Firefox](https://www.mozilla.org/firefox/)** - primary browser

## API Client / HTTP Debugging

- **[Bruno](https://www.usebruno.com)** - open-source API client; stores collections as plain-text files in your repo

## Package Manager

- **[pnpm](https://github.com/pnpm/pnpm)** - fast Node.js package manager

## Database Tools

- **[Sequel Ace](https://github.com/Sequel-Ace/Sequel-Ace)** - MySQL/MariaDB GUI
- **[Medis](https://github.com/luin/medis)** - Redis GUI client

## Window Management

- **[AeroSpace](https://github.com/nikitabobko/AeroSpace)** - tiling window manager

## Menu Bar Management

- **[Ice](https://github.com/jordanbaird/Ice)** - menu bar organiser
- **[NoTunes](https://github.com/tombonez/noTunes)** - prevents Apple Music from launching

## Screenshots

- **[Flameshot](https://github.com/flameshot-org/flameshot)** - cross-platform screenshot tool with annotation

## Mouse & Trackpad

- **[LinearMouse](https://linearmouse.app)** - mouse acceleration control
- **[Middle Click](https://middleclick.app)** - enables trackpad middle click

## App Switching

- **[AltTab](https://alt-tab-macos.netlify.app)** - Windows-style application switcher

## Image Editing

- **[Paintbrush](https://paintbrush.sourceforge.io/)** - simple Paint-like image editor

## Tool Version Manager

- **[mise](https://github.com/jdx/mise)** - polyglot tool version manager (replaces asdf / nvm / rbenv)

## Fonts

- **[JetBrains Mono Nerd Font](https://www.nerdfonts.com)** - primary coding font with Nerd Font icons
- **[Meslo LG Nerd Font](https://www.nerdfonts.com)** - fallback / terminal font

## IDE

- **[IntelliJ IDEA Community](https://www.jetbrains.com/idea/)** - free IDE, useful for Git merge conflict resolution

---

## Install via Homebrew

> Requires [Homebrew](https://brew.sh/) - install it first if not present:
> `sh -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"`

### CLI tools (`brew install`)

```bash
brew install atuin
brew install bat
brew install btop
brew install colima
brew install docker
brew install eza
brew install fd
brew install fzf
brew install git-delta
brew install difftastic
brew install direnv
brew install copilot-cli
brew install just
brew install lazygit
brew install lnav
brew install lychee
brew install mise
brew install mysql-client
brew install neovim
brew install pnpm
brew install ripgrep
brew install ripgrep-all
brew install semgrep
brew install shellcheck
brew install shfmt
brew install starship
brew install stripe/stripe-cli/stripe
brew install tldr
brew install tmux
brew install watchexec
brew install yazi
brew install yq
brew install zoxide
brew install zsh-autosuggestions
brew install zsh-syntax-highlighting
brew install bats-core
brew install actionlint
```

> `mysql-client` is often keg-only and may require adding Homebrew's bin path to your shell config.

### AI context packers (outside Homebrew core list)

```bash
brew install scc
npm install -g repomix
uv tool install files-to-prompt
cargo install code2prompt
```

### GUI apps (`brew install --cask`)

```bash
brew tap nikitabobko/tap
brew install --cask aerospace
brew install --cask bbedit
brew install --cask betterdisplay
brew install --cask bruno
brew install --cask ghostty
brew install --cask intellij-idea-ce
brew install --cask sequel-ace
```

### Installed outside Homebrew

| Tool      | Install method                                                                                    |
| --------- | ------------------------------------------------------------------------------------------------- |
| Oh My Zsh | `sh -c "$(curl -fsSL https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh)"` |
| lazy.nvim | Auto-installed by Neovim config on first launch                                                   |
| Medis     | [App Store](https://apps.apple.com/app/medis-gui-for-redis/id1063631769)                          |

## macOS Utilities (Non-Development)

These tools improve the daily macOS experience but are not part of the core development workflow.

- **[Stats](https://github.com/exelban/stats)** - menu bar system monitor
- **[Ice](https://github.com/jordanbaird/Ice)** - menu bar organiser
- **[NoTunes](https://github.com/tombonez/noTunes)** - prevents Apple Music from launching
- **[BetterDisplay](https://github.com/waydabber/BetterDisplay)** - display control
- **[Flameshot](https://github.com/flameshot-org/flameshot)** - screenshots with annotation
- **[LinearMouse](https://linearmouse.app)** - mouse acceleration control
- **[Middle Click](https://middleclick.app)** - trackpad middle click
- **[AltTab](https://alt-tab-macos.netlify.app)** - Windows-style application switcher
- **[Paintbrush](https://paintbrush.sourceforge.io/)** - simple Paint-like image editor
- **[Firefox](https://www.mozilla.org/firefox/)** - primary browser
- **[JetBrains Mono Nerd Font](https://www.nerdfonts.com)** - primary coding font
- **[Meslo LG Nerd Font](https://www.nerdfonts.com)** - fallback terminal font
