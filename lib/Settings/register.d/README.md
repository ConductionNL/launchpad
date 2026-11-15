# ADR-037: per-OpenSpec-change register fragments are merged here at load.
# Each change adds its own <change>.json (OpenAPI components.schemas/paths) instead
# of editing launchpad_register.json — concurrent builds never conflict.
#
# They are merged onto launchpad_register.json in file-name order by the
# ImportLaunchpadRegister repair step (objects key by key, lists joined), the
# same way OpenRegister's app host merges an app's register.d/. A fragment that
# changes something the importer does not compare by content (such as a
# schema's `configuration`) also needs ImportLaunchpadRegister::REGISTER_VERSION
# raised, or existing installs keep the old schema.
