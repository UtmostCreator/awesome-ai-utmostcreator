# Code Structure Search

Use when text search is too noisy or when syntax matters.

---

## Preferred Commands

Instead of regex grep for code structure use:

```bash
sg -p 'console.log($A)' -l ts src
```

Instead of unsafe regex rewrite use:

```bash
sg -p 'old($A)' -r 'new($A)' -l ts src
```

Instead of manual security grep use:

```bash
semgrep --config auto
```

For specific language rule:

```bash
semgrep -e 'eval($X)' --lang php app
```

---

## Use When

- finding function calls by syntax
- finding constructor patterns
- rewriting code safely
- searching JS/TS/PHP AST-level structures
- scanning semantic security risks

---

## Avoid

```bash
rg "function .*\("
sed -i 's/old/new/g' **/*.ts
```

Example: [`../examples/good-bad-code-structure-search.md`](../examples/good-bad-code-structure-search.md)
