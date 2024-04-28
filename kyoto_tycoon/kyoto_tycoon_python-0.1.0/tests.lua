kt = __kyototycoon__

if kt.thid == 0 then
    kt.log("system", "tests.lua loaded")
end


function test1(inmap, outmap)
    for key, val in pairs(inmap) do
        outmap['outk' .. key] = 'outv' .. val
    end
    return kt.RVSUCCESS
end


function test2(inmap, outmap)
    return kt.RVEINTERNAL
end


function test3(inmap, outmap)
    return kt.RVELOGIC
end


function test4(inmap, outmap)
    return kt.RVEINVALID
end
