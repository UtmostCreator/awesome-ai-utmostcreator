// ~/.zshrc
# Enable Powerlevel10k instant prompt. Should stay close to the top of ~/.zshrc.
# if [[ -r "${XDG_CACHE_HOME:-$HOME/.cache}/p10k-instant-prompt-${(%):-%n}.zsh" ]]; then
#   source "${XDG_CACHE_HOME:-$HOME/.cache}/p10k-instant-prompt-${(%):-%n}.zsh"
# fi

# Oh My Zsh
export ZSH="$HOME/.oh-my-zsh"
# ZSH_THEME="powerlevel10k/powerlevel10k"  # disabled in favour of Starship
ZSH_THEME=""  # must be blank when using Starship

# zsh-autosuggestions configuration MUST be set before plugin is loaded
ZSH_AUTOSUGGEST_HIGHLIGHT_STYLE="fg=#6c71c4"
ZSH_AUTOSUGGEST_STRATEGY=(history completion)

# Plugins must be declared before sourcing oh-my-zsh.sh
plugins=(
  git
  zsh-autosuggestions
)

source "$ZSH/oh-my-zsh.sh"

# Herd injected PHP binary.
export PATH="$HOME/Library/Application Support/Herd/bin/:$PATH"
export HERD_PHP_84_INI_SCAN_DIR="$HOME/Library/Application Support/Herd/config/php/84/"
export HERD_PHP_83_INI_SCAN_DIR="$HOME/Library/Application Support/Herd/config/php/83/"
export PATH="$PATH:/Applications/Herd.app/Contents/MacOS"

# NVM
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
[ -s "$NVM_DIR/bash_completion" ] && . "$NVM_DIR/bash_completion"

# Homebrew mysql-client
export PATH="/opt/homebrew/opt/mysql-client/bin:$PATH"

# Powerlevel10k config (disabled)
# [[ ! -f ~/.p10k.zsh ]] || source ~/.p10k.zsh

# Starship prompt
export STARSHIP_CONFIG="$HOME/.config/starship/starship.toml"
eval "$(starship init zsh)"

# Allow listing up to 50 matches without asking (tune to preference).
LISTMAX=50

# Tab: regular completion
bindkey '^I' expand-or-complete

# Double Tab: accept autosuggestion
bindkey '^I^I' autosuggest-accept

# Shift+Tab: accept autosuggestion (common escape sequence)
# bindkey '^[[Z' autosuggest-accept

export NODE_EXTRA_CA_CERTS="$HOME/Library/Application Support/Herd/config/valet/CA/LaravelValetCASelfSigned.pem"

if [[ -f "$HOME/.secrets" ]]; then
  source "$HOME/.secrets"
fi

source /opt/homebrew/share/zsh-syntax-highlighting/zsh-syntax-highlighting.zsh

# fzf shell integration (key bindings: Ctrl+R history, Ctrl+T files, Alt+C cd)
eval "$(fzf --zsh)"

# zoxide
eval "$(zoxide init zsh)"

# Yazi shell wrapper — changes directory on exit
function yy() {
    local tmp cwd
    tmp="$(mktemp -t "yazi-cwd.XXXXXX")"
    yazi "$@" --cwd-file="$tmp"
    if cwd="$(command cat -- "$tmp")" && [ -n "$cwd" ] && [ "$cwd" != "$PWD" ]; then
        builtin cd -- "$cwd"
    fi
    rm -f -- "$tmp"
}

# CMS dev workspace
alias cms='bash ~/scripts/cms-dev.sh'
export APP_CONFIGS_HOME="${APP_CONFIGS_HOME:-$HOME/workspace/app-configs}"
alias acfg='cd "$APP_CONFIGS_HOME"'

function acfg-doctor() {
    if command -v just >/dev/null 2>&1; then
        (cd "$APP_CONFIGS_HOME" && just doctor)
    else
        (cd "$APP_CONFIGS_HOME" && bash scripts/doctor.sh)
    fi
}

function acfg-ai-check() {
    if command -v just >/dev/null 2>&1; then
        (cd "$APP_CONFIGS_HOME" && just ai-check)
    else
        (
            cd "$APP_CONFIGS_HOME" &&
            php tools/ai/validate-ai-config.php &&
            php tools/ai/validate-ai-catalog.php &&
            php tools/ai/generate-ai-catalog.php --check
        )
    fi
}

export ATUIN_TMUX_POPUP=false
# shellcheck disable=SC2034,SC2153,SC2086,SC2155

# Above line is because shellcheck doesn't support zsh, per
# https://github.com/koalaman/shellcheck/wiki/SC1071, and the ignore: param in
# ludeeus/action-shellcheck only supports _directories_, not _files_. So
# instead, we manually add any error the shellcheck step finds in the file to
# the above line ...

# Source this in your ~/.zshrc
autoload -U add-zsh-hook

zmodload zsh/datetime 2>/dev/null

# If zsh-autosuggestions is installed, configure it to use Atuin's search. If
# you'd like to override this, then add your config after the $(atuin init zsh)
# in your .zshrc
_zsh_autosuggest_strategy_atuin() {
    # silence errors, since we don't want to spam the terminal prompt while typing.
    suggestion=$(ATUIN_QUERY="$1" atuin search --cmd-only --limit 1 --search-mode prefix 2>/dev/null)
}

if [ -n "${ZSH_AUTOSUGGEST_STRATEGY:-}" ]; then
    ZSH_AUTOSUGGEST_STRATEGY=("atuin" "${ZSH_AUTOSUGGEST_STRATEGY[@]}")
else
    ZSH_AUTOSUGGEST_STRATEGY=("atuin")
fi

if [[ -z "${ATUIN_SESSION:-}" || "${ATUIN_SHLVL:-}" != "$SHLVL" ]]; then
    export ATUIN_SESSION=$(atuin uuid)
    export ATUIN_SHLVL=$SHLVL
fi
ATUIN_HISTORY_ID=""

_atuin_preexec() {
    local id
    id=$(atuin history start -- "$1" 2>/dev/null)
    export ATUIN_HISTORY_ID="$id"
    __atuin_preexec_time=${EPOCHREALTIME-}
}

_atuin_precmd() {
    local EXIT="$?" __atuin_precmd_time=${EPOCHREALTIME-}

    [[ -z "${ATUIN_HISTORY_ID:-}" ]] && return

    local duration=""
    if [[ -n $__atuin_preexec_time && -n $__atuin_precmd_time ]]; then
        printf -v duration %.0f $(((__atuin_precmd_time - __atuin_preexec_time) * 1000000000))
    fi

    (ATUIN_LOG=error atuin history end --exit $EXIT ${duration:+--duration=$duration} -- $ATUIN_HISTORY_ID &) >/dev/null 2>&1
    export ATUIN_HISTORY_ID=""
}

# Check if tmux popup is available (tmux >= 3.2)
__atuin_tmux_popup_check() {
    [[ -n "${TMUX-}" ]] || return 1
    [[ "${ATUIN_TMUX_POPUP:-true}" != "false" ]] || return 1

    # https://github.com/tmux/tmux/wiki/FAQ#how-often-is-tmux-released-what-is-the-version-number-scheme
    local tmux_version
    tmux_version=$(tmux -V 2>/dev/null | sed -n 's/^[^0-9]*\([0-9][0-9]*\.[0-9][0-9]*\).*/\1/p') # Could have used grep...
    [[ -z "$tmux_version" ]] && return 1

    local m1 m2
    m1=${tmux_version%%.*}
    m2=${tmux_version#*.}
    m2=${m2%%.*}
    [[ "$m1" =~ ^[0-9]+$ ]] || return 1
    [[ "$m2" =~ ^[0-9]+$ ]] || m2=0
    (( m1 > 3 || (m1 == 3 && m2 >= 2) ))
}

# Use global variable to fix scope issues with traps
__atuin_popup_tmpdir=""
__atuin_tmux_popup_cleanup() {
    [[ -n "$__atuin_popup_tmpdir" && -d "$__atuin_popup_tmpdir" ]] && command rm -rf "$__atuin_popup_tmpdir"
    __atuin_popup_tmpdir=""
}

__atuin_search_cmd() {
    local -a search_args=("$@")

    if __atuin_tmux_popup_check; then
        __atuin_popup_tmpdir=$(mktemp -d) || return 1
        local result_file="$__atuin_popup_tmpdir/result"

        trap '__atuin_tmux_popup_cleanup' EXIT HUP INT TERM

        local escaped_query escaped_args
        escaped_query=$(printf '%s' "$BUFFER" | sed "s/'/'\\\\''/g")
        escaped_args=""
        for arg in "${search_args[@]}"; do
            escaped_args+=" '$(printf '%s' "$arg" | sed "s/'/'\\\\''/g")'"
        done

        # In the popup, atuin goes to terminal, stderr goes to file
        local cdir popup_width popup_height
        cdir=$(pwd)
        popup_width="${ATUIN_TMUX_POPUP_WIDTH:-80%}" # Keep default value anyways
        popup_height="${ATUIN_TMUX_POPUP_HEIGHT:-60%}"
        tmux display-popup -d "$cdir" -w "$popup_width" -h "$popup_height" -E -E -- \
            sh -c "PATH='$PATH' ATUIN_SESSION='$ATUIN_SESSION' ATUIN_SHELL=zsh ATUIN_LOG=error ATUIN_QUERY='$escaped_query' atuin search $escaped_args -i 2>'$result_file'"

        if [[ -f "$result_file" ]]; then
            cat "$result_file"
        fi

        __atuin_tmux_popup_cleanup
        trap - EXIT HUP INT TERM
    else
        ATUIN_SHELL=zsh ATUIN_LOG=error ATUIN_QUERY=$BUFFER atuin search "${search_args[@]}" -i 3>&1 1>&2 2>&3
    fi
}

_atuin_search() {
    emulate -L zsh
    zle -I

    # swap stderr and stdout, so that the tui stuff works
    # TODO: not this
    local output
    # shellcheck disable=SC2048
    output=$(__atuin_search_cmd $*)

    zle reset-prompt
    # re-enable bracketed paste
    # shellcheck disable=SC2154
    echo -n ${zle_bracketed_paste[1]} >/dev/tty

    if [[ -n $output ]]; then
        RBUFFER=""
        LBUFFER=$output

        if [[ $LBUFFER == __atuin_accept__:* ]]
        then
            LBUFFER=${LBUFFER#__atuin_accept__:}
            zle accept-line
        fi
    fi
}
_atuin_search_vicmd() {
    _atuin_search --keymap-mode=vim-normal
}
_atuin_search_viins() {
    _atuin_search --keymap-mode=vim-insert
}

_atuin_up_search() {
    # Only trigger if the buffer is a single line
    if [[ ! $BUFFER == *$'\n'* ]]; then
        _atuin_search --shell-up-key-binding "$@"
    else
        zle up-line
    fi
}
_atuin_up_search_vicmd() {
    _atuin_up_search --keymap-mode=vim-normal
}
_atuin_up_search_viins() {
    _atuin_up_search --keymap-mode=vim-insert
}

add-zsh-hook preexec _atuin_preexec
add-zsh-hook precmd _atuin_precmd

zle -N atuin-search _atuin_search
zle -N atuin-search-vicmd _atuin_search_vicmd
zle -N atuin-search-viins _atuin_search_viins
zle -N atuin-up-search _atuin_up_search
zle -N atuin-up-search-vicmd _atuin_up_search_vicmd
zle -N atuin-up-search-viins _atuin_up_search_viins

# These are compatibility widget names for "atuin <= 17.2.1" users.
zle -N _atuin_search_widget _atuin_search
zle -N _atuin_up_search_widget _atuin_up_search

bindkey -M emacs '^r' atuin-search
bindkey -M viins '^r' atuin-search-viins
bindkey -M vicmd '/' atuin-search
bindkey -M emacs '^[[A' atuin-up-search
bindkey -M vicmd '^[[A' atuin-up-search-vicmd
bindkey -M viins '^[[A' atuin-up-search-viins
bindkey -M emacs '^[OA' atuin-up-search
bindkey -M vicmd '^[OA' atuin-up-search-vicmd
bindkey -M viins '^[OA' atuin-up-search-viins
bindkey -M vicmd 'k' atuin-up-search-vicmd
# TUI uses an alternate screen, so no explicit cleanup is needed.
_atuin_ai_cleanup() {
    true
}

# Question mark at start of line - natural language mode
_atuin_ai_question_mark() {
    # If buffer is empty or just contains '?', trigger natural language mode
    if [[ -z "$BUFFER" || "$BUFFER" == "?" ]]; then
        BUFFER=""
        local output
        output=$(atuin ai inline --hook 3>&1 1>&2 2>&3)

        # Clean up the inline viewport
        _atuin_ai_cleanup

        if [[ $output == __atuin_ai_print__:* ]]; then
            zle -I
            echo "${output#__atuin_ai_print__:}"
        elif [[ $output == __atuin_ai_cancel__ ]]; then
            zle reset-prompt
        elif [[ $output == __atuin_ai_execute__:* ]]; then
            RBUFFER=""
            LBUFFER=${output#__atuin_ai_execute__:}
            zle reset-prompt
            zle accept-line
        elif [[ $output == __atuin_ai_insert__:* ]]; then
            RBUFFER=""
            LBUFFER=${output#__atuin_ai_insert__:}
            zle reset-prompt
        elif [[ -n $output ]]; then
            RBUFFER=""
            LBUFFER=$output
            zle reset-prompt
        else
            zle reset-prompt
        fi
    else
        LBUFFER="${LBUFFER}?"
    fi
}

# Set up keybindings
zle -N _atuin_ai_question_mark
bindkey '?' _atuin_ai_question_mark # Question mark


# update all packages, list them here
brewup() {
  local formulae=(
    atuin bat btop colima docker docker-compose eza fd fzf git-delta just
    lazygit lnav mise mysql-client neovim pnpm ripgrep ripgrep-all starship
    tldr tmux yazi zoxide zsh-autosuggestions zsh-syntax-highlighting
  )

  local casks=(
    aerospace bruno firefox flameshot
    font-jetbrains-mono-nerd-font font-meslo-lg-nerd-font ghostty
    jordanbaird-ice linearmouse notunes sequel-ace stats
    intellij-idea-ce alt-tab
  )

  local installed_formulae=()
  local installed_casks=()
  local item

  for item in "${formulae[@]}"; do
    if brew list --formula "$item" >/dev/null 2>&1; then
      installed_formulae+=("$item")
    fi
  done

  for item in "${casks[@]}"; do
    if brew list --cask "$item" >/dev/null 2>&1; then
      installed_casks+=("$item")
    fi
  done

  brew update || return 1

  if (( ${#installed_formulae[@]} )); then
    brew upgrade "${installed_formulae[@]}" || return 1
  fi

  if (( ${#installed_casks[@]} )); then
    brew upgrade --cask "${installed_casks[@]}" || return 1
  fi

  if command -v omz >/dev/null 2>&1; then
    omz update || return 1
  fi

  brew cleanup
}
