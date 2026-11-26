import test from 'node:test'
import assert from 'node:assert/strict'
import { tcKimlikNoGecerli } from '../javascript/tc-kimlik-no.mjs'

test('sentetik kontrol basamağı örnekleri ve negatif modulo durumu', () => {
  for (const numara of ['10000000146', '19000000088']) assert.equal(tcKimlikNoGecerli(numara), true)
})

test('bozuk biçim, bozuk basamak, baştaki sıfır ve sayısal girdi reddedilir', () => {
  for (const numara of ['', '10000000145', '00000000000', '01234567890', '1000000014', '100000001460', '1000000014a', ' 10000000146', '10000000146\n', '１０００００００１４６', '100 00000146', 10000000146, null, undefined]) {
    assert.equal(tcKimlikNoGecerli(numara), false)
  }
})
