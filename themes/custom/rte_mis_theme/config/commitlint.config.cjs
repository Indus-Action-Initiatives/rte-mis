module.exports = {
  rules: {
    "header-max-length": [2, "always", 100],
    "subject-empty": [2, "never"],
  },
  parserPreset: {
    parserOpts: {
      headerPattern: /^([A-Z]{2,})-(\d+): \[([A-Za-z0-9_-]+)\] (.+)$/,
      headerCorrespondence: ["project", "ticket", "branch", "subject"],
      errorMessage:
        "Please follow the commit message format: GCC-206: [preprod] Your subject here",
    },
  },
};
