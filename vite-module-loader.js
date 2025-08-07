import fs from 'fs/promises';
import path from 'path';
import { fileURLToPath, pathToFileURL } from 'url';

// Derive __dirname from import.meta.url
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function collectModuleAssetsPaths(paths, modulesPath) {
  const mainPaths = paths || [];
  const resolvedModulesPath = path.join(__dirname, modulesPath);
  const moduleStatusesPath = path.join(__dirname, 'modules_statuses.json');

  // Check if Modules directory exists
  try {
    await fs.access(resolvedModulesPath);
  } catch {
    console.warn(`⚠️ Modules directory not found at ${resolvedModulesPath}, skipping module asset loading.`);
    return mainPaths;
  }

  // Check if modules_statuses.json exists
  let moduleStatuses = {};
  try {
    const moduleStatusesContent = await fs.readFile(moduleStatusesPath, 'utf-8');
    moduleStatuses = JSON.parse(moduleStatusesContent);
  } catch {
    console.warn(`⚠️ modules_statuses.json not found at ${moduleStatusesPath}, assuming no modules are enabled.`);
    return mainPaths;
  }

  try {
    const moduleDirectories = await fs.readdir(resolvedModulesPath);

    for (const moduleDir of moduleDirectories) {
      if (moduleDir.startsWith('.') || moduleDir === '__MACOSX') continue;

      if (moduleStatuses[moduleDir] === true) {
        const viteConfigPath = path.join(resolvedModulesPath, moduleDir, 'vite.config.js');

        try {
          await fs.access(viteConfigPath);
          const moduleConfigURL = pathToFileURL(viteConfigPath);
          const moduleConfig = await import(moduleConfigURL.href);

          if (moduleConfig.default?.paths?.length) {
            mainPaths.push(...moduleConfig.default.paths);
          } else {
            console.warn(`⚠️ Module '${moduleDir}' vite.config.js has no valid 'paths' array.`);
          }
        } catch (error) {
          console.warn(`⚠️ vite.config.js not found or invalid for module '${moduleDir}': ${error.message}`);
        }
      }
    }
  } catch (error) {
    console.error(`❌ Failed reading modules: ${error.message}`);
  }

  return mainPaths;
}

export default collectModuleAssetsPaths;
