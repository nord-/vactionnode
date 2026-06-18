const env = import.meta.env;

export default {
  password: env.VITE_PASSWORD,
  github: {
    token: env.VITE_GITHUB_PAT,
    owner: env.VITE_GITHUB_OWNER,
    repo: env.VITE_GITHUB_REPO,
    branch: env.VITE_GITHUB_BRANCH || 'main',
    registryPath: env.VITE_REGISTRY_PATH || 'data/registry.json',
    vacationsPath: env.VITE_VACATIONS_PATH || 'data/vacations.json',
  },
};
