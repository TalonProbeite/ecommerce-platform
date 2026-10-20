import assert from 'node:assert/strict'
import { test } from 'node:test'
import { loadHttpErrors } from './http-errors.mjs'
import { createServer } from 'vite'
import { createElement } from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { MemoryRouter } from 'react-router-dom'

const { HttpError } = await loadHttpErrors()

async function context() {
 const server = await createServer({ configFile: false, envDir: false,
  resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
  define: { 'import.meta.env.VITE_API_MODE': '"real"', 'import.meta.env.VITE_API_URL': '"https://rentify.test"' },
  server: { middlewareMode: true, watch: null, ws: false }, appType: 'custom' })
 return { server, ...(await server.ssrLoadModule('/src/api/client.ts')) }
}
const profile = { email: 'user@gmail.com', first_name: 'Jane', last_name: 'Doe', phone: '+48123456789', role: 'customer', is_verified: true, is_oauth: true }
function failed(config, status) { return new HttpError('expired', String(status), config, {status,data:{code:'oauth_registration_expired'},headers:{},config}) }

test('OAuth pending profile uses credentials, safe mapping and no refresh on expiry', async () => {
 const {server,api}=await context()
 try {
  const {loadOAuthProfile}=await server.ssrLoadModule('/src/services/auth.ts')
  const paths=[]
  api.defaults.adapter=async config=>{ paths.push(config.url); assert.equal(config.credentials,'include'); return {status:200,data:{...profile,sub:'secret',profile_key:'secret'},headers:{},config} }
  assert.deepEqual(await loadOAuthProfile(), { email:'user@gmail.com',firstName:'Jane',lastName:'Doe' })
  api.defaults.adapter=async config=>{paths.push(config.url);throw failed(config,401)}
  await assert.rejects(loadOAuthProfile())
  assert.deepEqual(paths,['/auth/api/oauth/profile','/auth/api/oauth/profile'])
  for (const data of [null,{}, {...profile,email:3},{...profile,last_name:null}]) {
   api.defaults.adapter=async config=>({status:200,data,headers:{},config})
   await assert.rejects(loadOAuthProfile(),/invalid.*profile/i)
  }
 } finally {await server.close()}
})
test('OAuth completion reloads the real profile and sets authenticated state',async()=>{
 const {server,api}=await context()
 try {
  const {useAuthStore}=await server.ssrLoadModule('/src/store/auth-store.ts')
  const calls=[]
  api.defaults.adapter=async config=>{calls.push({url:config.url,body:config.data?JSON.parse(config.data):undefined});return {status:200,data:config.url.endsWith('/complete')?{message:'successfully registered'}:profile,headers:{},config}}
  const user=await useAuthStore.getState().completeOAuth({firstName:'Jane',lastName:'Doe',phone:'+48123456789'})
  assert.equal(user.isOAuth,true);assert.equal(useAuthStore.getState().status,'authenticated')
  assert.deepEqual(calls,[{url:'/auth/api/oauth/complete',body:{first_name:'Jane',last_name:'Doe',phone:'+48123456789'}},{url:'/auth/api/private/profile',body:undefined}])
 }finally{await server.close()}
})
test('OAuth profile mapping rejects invalid OAuth flags',async()=>{
 const {server,api}=await context()
 try{
  const {loadAccountProfile}=await server.ssrLoadModule('/src/services/auth.ts')
  api.defaults.adapter=async config=>({status:200,data:{...profile,is_oauth:'true'},headers:{},config})
  await assert.rejects(loadAccountProfile(),/invalid profile/)
 }finally{await server.close()}
})
test('OAuth pages render accessible loading states and explain Google registration',async()=>{
 const {server}=await context()
 try{
  const {OAuthCompletePage}=await server.ssrLoadModule('/src/pages/oauth-complete-page.tsx')
  const {OAuthSuccessPage}=await server.ssrLoadModule('/src/pages/oauth-success-page.tsx')
  const render=Page=>renderToStaticMarkup(createElement(MemoryRouter,null,createElement(Page)))
  assert.match(render(OAuthCompletePage),/Complete your profile/)
  assert.match(render(OAuthCompletePage),/role="status"/)
  assert.match(render(OAuthSuccessPage),/Signing you in/)
 }finally{await server.close()}
})

test('successful completion with a failed profile read requests session restoration, not a second registration', async()=>{
 const {server,api}=await context()
 try{
  const {useAuthStore,OAuthSessionRestoreError}=await server.ssrLoadModule('/src/store/auth-store.ts')
  const paths=[]
  api.defaults.adapter=async config=>{paths.push(config.url);return {status:200,data:config.url.endsWith('/complete')?{message:'successfully registered'}:{invalid:true},headers:{},config}}
  await assert.rejects(useAuthStore.getState().completeOAuth({firstName:'Jane',phone:'+48123456789'}), error=>error instanceof OAuthSessionRestoreError)
  api.defaults.adapter=async config=>{paths.push(config.url);return {status:200,data:profile,headers:{},config}}
  assert.equal((await useAuthStore.getState().restoreOAuthSession()).isOAuth,true)
  assert.equal(paths.filter(path=>path.endsWith('/complete')).length,1)
 }finally{await server.close()}
})

test('a late guest probe cannot overwrite completed OAuth authentication',async()=>{
 const {server,api}=await context()
 try{
  const {useAuthStore}=await server.ssrLoadModule('/src/store/auth-store.ts')
  let release
  let first=true
  api.defaults.adapter=async config=>{
   if(first){first=false;return new Promise(resolve=>{release=()=>resolve({status:200,data:{...profile,email:'old@example.com',is_oauth:false},headers:{},config})})}
   return {status:200,data:config.url.endsWith('/complete')?{message:'registered'}:profile,headers:{},config}
  }
  const bootstrap=useAuthStore.getState().initialize()
  while(!release)await new Promise(resolve=>setTimeout(resolve,0))
  await useAuthStore.getState().completeOAuth({firstName:'Jane',phone:'+48123456789'})
  release();await bootstrap
  assert.equal(useAuthStore.getState().user.email,profile.email)
  assert.equal(useAuthStore.getState().user.isOAuth,true)
 }finally{await server.close()}
})
