# Open Source Checklist

Before making the GitHub repository public:

- Confirm `.env` is not committed.
- Confirm `runtime/` is not committed.
- Confirm `public/install/install.lock` and `public/install/1install.lock` are not committed.
- Confirm `public/error.log` is not committed.
- Confirm `data/pan_tree_cache/` contains only `.gitkeep` and `README.md`.
- Search for real passwords, cookies, tokens, `stoken`, private IPs, and private domains.
- Install from a fresh database once using `/install/index.php`.
- Replace project screenshots or docs that reveal private server addresses.

Recommended GitHub creation settings:

- Repository name: `yuexin-search`
- Visibility: start with Private, switch to Public after the checks above.
- Add README: Off
- Add .gitignore: No .gitignore
- Add license: None in GitHub UI if the local `LICENSE` file is added later.
 