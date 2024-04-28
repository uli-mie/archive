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

