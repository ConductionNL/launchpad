# ADR-037: per-OpenSpec-change register fragments are merged here at load.
# Each change adds its own <change>.json (OpenAPI components.schemas/paths) instead
# of editing launchpad_register.json — concurrent builds never conflict.
#
# NOTE: LaunchPad does not currently load launchpad_register.json into OpenRegister
# (there is no ConfigurationService::importFromApp call). This directory is staged
# for when a register-import loader is added; the merge enabler lives in src/main.js
# (manifest fragments) for now. See the ADR-037 PR notes.
