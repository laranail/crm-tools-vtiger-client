# Security Policy

## Supported Versions

We release patches for security vulnerabilities for the latest minor release.

| Version | Supported          |
| ------- | ------------------ |
| 0.x     | :white_check_mark: |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, report them via email to **opensource@simtabi.com**.

You should receive a response within 48 hours. If you do not, please follow up
to ensure we received your original message.

Please include as much of the following as you can, to help us triage quickly:

* Type of issue (e.g. path traversal, SQL injection, XSS, SSRF, etc.)
* Full paths of the source file(s) involved
* The location of the affected code (tag/branch/commit or direct URL)
* Any configuration required to reproduce
* Step-by-step reproduction instructions
* Proof-of-concept or exploit code (if possible)
* Impact, including how an attacker might exploit the issue

## Policy

We follow [Coordinated Vulnerability Disclosure](https://vuls.cert.org/confluence/display/CVD).
We will acknowledge your report within 48 hours, keep you updated on progress,
develop and test a fix, release a patched version, and then publicly disclose.
Credit is given to reporters unless anonymity is requested.

## Security Best Practices for Users

1. **Keep updated** — always use the latest stable version.
2. **Validate input** — validate and sanitize user input before processing.
3. **Use HTTPS** in production.
4. **File permissions** — ensure proper file and directory permissions.
5. **Secrets** — never commit credentials; use environment variables.

Thank you for helping keep Laranail Toolkit and its users safe.
