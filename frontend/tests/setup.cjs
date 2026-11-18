const path = require('node:path')
const Module = require('node:module')

const srcRoot = path.join(__dirname, '..', '.test-dist', 'src')
const resolveFilename = Module._resolveFilename

Module._resolveFilename = function (request, parent, isMain, options) {
  if (request.startsWith('@/')) {
    request = path.join(srcRoot, request.slice(2))
  }
  return resolveFilename.call(this, request, parent, isMain, options)
}
